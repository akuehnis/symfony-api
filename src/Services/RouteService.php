<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\Common\Annotations\AnnotationReader;
use Akuehnis\SymfonyApi\Converter\ObjectConverter;

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
        $response_converters[400] = new \Akuehnis\SymfonyApi\Converter\ObjectConverter([
            'class_name' => 'Akuehnis\SymfonyApi\Models\Response400'
        ]);
        foreach ($this->getRouteAnnotations($route) as $annotation){
            if (
                get_class($annotation) == 'Akuehnis\SymfonyApi\Annotations\Response' ||
                is_subclass_of($annotation, 'Akuehnis\SymfonyApi\Annotations\Response')
            ){
                $response_converters[$annotation->status] =  new \Akuehnis\SymfonyApi\Converter\ObjectConverter([
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
        $name = $parameter_reflection->getName();
        $defaultValue = null;
        if ($parameter_reflection->isDefaultValueAvailable()){
            $defaultValue = $parameter_reflection->getDefaultValue();
        }
        $converter = null;
        // Try to find converter for this parameter from annotations
        $annotations = $this->getRouteAnnotations($route);
        $body_annotations = array_filter($annotations, function($item) use ($name) {
            return get_class($item) == 'Akuehnis\SymfonyApi\Annotations\Body'
                && $item->getName() == $name
                ;     
        });
        $body_annotation = array_shift($body_annotations);
        if ($body_annotation){
            $class_name = $body_annotation->getClassName();
            if (null === $class_name){
                $reflection_type = $parameter_reflection->getType();
                if (null === $reflection_type) {
                    throw new \Exception('Body annotation found but classname is not found. 
                    Try typehinting the controller paremeter or define class_name in annotation.');
                }
                $class_name = $reflection_type->getName();
            }
            $args = [
                'class_name' => $class_name,
                'is_array' => $body_annotation->isArray(),
                'name' => $body_annotation->getName(),
                'required' => !$parameter_reflection->isDefaultValueAvailable(),
                'nullable' => $parameter_reflection->allowsNull(),
            ];
            if ($parameter_reflection->isDefaultValueAvailable()){
                $args['default_value'] = $parameter_reflection->getDefaultValue();
            }
            $converter =  new ObjectConverter($args);
        }
        if (!$converter){
            $converter_annotations = array_filter($annotations, function($item) use ($name) {
                return is_subclass_of(get_class($item), 'Akuehnis\SymfonyApi\Converter\ValueConverter') &&
                    $item->getName() == $name;
            });
            $converter = array_shift($converter_annotations);
        }
        // Wenn Parameter nicht Type-Hinted ist, dann ist reflection_type null
        $reflection_type = $parameter_reflection->getType();
        $type = $reflection_type ? $reflection_type->getName() : null;
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
        if ($converter){
            $location = ['query'];
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $location = ['path']; 
            }
            if (
                get_class($converter) =='Akuehnis\SymfonyApi\Converter\ObjectConverter' ||
                is_subclass_of(get_class($converter), 'Akuehnis\SymfonyApi\Converter\ObjectConverter')
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