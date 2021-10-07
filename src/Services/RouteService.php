<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\Common\Annotations\AnnotationReader;

class RouteService
{

    protected $router;


    public function __construct(
        UrlGeneratorInterface $UrlGeneratorInterface
    ){
        $this->router = $UrlGeneratorInterface;
    }

    public function getMethodReflection($route){
        if (!method_exists($route, 'getDefaults')){
            return null;
        }
        $defaults = $route->getDefaults();
        if (!isset($defaults['_controller']) || false === strpos($defaults['_controller'], '::')){
            return null;
        }
        list($class, $method) = explode('::', $defaults['_controller']);
        if (!class_exists($class)){
            return null;
        }
        $reflection = new \ReflectionMethod($class, $method);

        return $reflection;
    }

    public function getRouteByName($name){
        return $this->router->getRouteCollection()->get($name);
    }

    public function getRouteFromRequest($request) 
    {
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return null;
        }
        $route = $this->getRouteByName($routeName);
        return $route;
    }

    public function getRouteParamConverters($route):array
    {
        if (!$this->isApiRoute($route)){
            return [];
        }
        $annotationReader = new AnnotationReader();
        $reflection = $this->getMethodReflection($route);
        if (!$reflection){
            return [];
        }
        $converters = [];
        foreach ($reflection->getParameters() as $parameter){
            $converter = $this->getParameterConverter($route, $parameter);
            if ($converter){
                $converters[] = $converter;
            }
            continue;
        }
        return $converters;
    }

    public function getRouteAnnotations($route, ?string $filter_class = null){
        $annotationReader = new AnnotationReader();
        $reflection = $this->getMethodReflection($route);
        if (!$reflection){
            return [];
        }
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        if ($filter_class){
            $annotations = array_filter($annotations, function($item) use ($filter_class) {
                return get_class($item) == $filter_class;
            });
        }

        return $annotations;
    }


    /**
     * Returns the converter for a method parameter
     */
    public function getParameterConverter($route, $parameter_reflection){
        $reflection_type = $parameter_reflection->getType();
        $name = $parameter_reflection->getName();
        $type = $reflection_type->getName();
        $defaultValue = null;
        if ($parameter_reflection->isDefaultValueAvailable()){
            $defaultValue = $parameter_reflection->getDefaultValue();
        }
        if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ValueConverter')){
            // From PHP 8.1 Objects can be passed as default value. This should extend ValueConverter
            $converter = $defaultValue;
        } else {
            // Try to find converter for this parameter from annotations
            $annotations = $this->getRouteAnnotations($route);
            $converter_annotations = array_filter($annotations, function($item) use ($name) {
                return is_subclass_of(get_class($item), 'Akuehnis\SymfonyApi\Converter\ValueConverter') &&
                    $item->getName() == $name;
            });
            
            $converter = array_shift($converter_annotations);
        }
        if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){

            // For base types we have a converter ready to use
            $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            if ($parameter_reflection->isDefaultValueAvailable()){
                $converter = new $className([
                    'default_value' => $parameter_reflection->getDefaultValue(),
                    'required' => false,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                ]);
            } else {
                $converter = new $className([
                    'required' => true,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                ]);
            }
        }
        if (!$converter && is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            $className = 'Akuehnis\SymfonyApi\Converter\BaseModelConverter';
            if ($parameter_reflection->isDefaultValueAvailable()){
                $converter = new $className([
                    'default_value' => $parameter_reflection->getDefaultValue(),
                    'required' => false,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                    'class_name' => $type,
                ]);   
            } else {
                $converter = new $className([
                    'required' => true,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                    'class_name' => $type,
                ]);
            }
        }
        if ($converter){
            $location = ['query'];
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $location = ['path']; 
            }
            if (
                get_class($converter) =='Akuehnis\SymfonyApi\Converter\BaseModelConverter' ||
                is_subclass_of(get_class($converter), 'Akuehnis\SymfonyApi\Converter\BaseModelConverter')
                ){
                $location = ['body'];
            }
            $converter->setLocation($location);
        }
        return $converter;
    }

    public function isApiRoute($route) {
        $api_annotations = $this->getRouteAnnotations($route, 'Akuehnis\SymfonyApi\Annotations\Tag');
        return 0 < count($api_annotations);
    }


}