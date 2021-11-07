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

    public function getControllerClass($route)
    {
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
        return $class;

    }

    /**
     * used in QueryResover and RequestValidationSubscriber
     */
    public function getRouteFromRequest($request) 
    {
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return null;
        }
        $route = $this->router->getRouteCollection()->get($routeName);;
        return $route;
    }

    public function getRouteResponseConverters($route) 
    {
        $response_converters = [];
        $response_converters[200] = new \Akuehnis\SymfonyApi\Converter\StringConverter();
        $response_converters[400] = new \Akuehnis\SymfonyApi\Converter\BaseModelConverter([
            'class_name' => 'Akuehnis\SymfonyApi\Models\Response400'
        ]);
        foreach ($this->getRouteAnnotations($route) as $annotation){
            if (
                get_class($annotation) == 'Akuehnis\SymfonyApi\Annotations\Response' ||
                is_subclass_of($annotation, 'Akuehnis\SymfonyApi\Annotations\Response')
            ){
                $response_converters[$annotation->status] =  new \Akuehnis\SymfonyApi\Converter\BaseModelConverter([
                    'class_name' => $annotation->class_name
                ]);
            }
        }
        return $response_converters;
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

    public function getRouteParamConverters($route):array
    {
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

    public function getRouteDocComment($route)
    {
        $reflection = $this->getMethodReflection($route);
        return $reflection->getDocComment();
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
            if ('array' == $type){
                // Defaults to String of arrays
                $className = 'Akuehnis\SymfonyApi\Converter\StringConverter';
            } else {
                $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            }
            
            if ($parameter_reflection->isDefaultValueAvailable()){
                $converter = new $className([
                    'default_value' => $parameter_reflection->getDefaultValue(),
                    'required' => false,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                    'is_array' => 'array' == $type,
                ]);
            } else {
                $converter = new $className([
                    'required' => true,
                    'nullable' => $parameter_reflection->allowsNull(),
                    'name' => $name,
                    'is_array' => 'array' == $type,
                ]);
            }
        }
        if (!$converter && is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\BaseModel')){
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
            if ('path' == $location[0]){
                $converter->setRequired(true);
            }
        }
        return $converter;
    }

}