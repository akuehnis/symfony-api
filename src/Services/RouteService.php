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

    public function isApiRoute($request) {
        $annotationReader = new AnnotationReader();
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return false;
        }
        $route = $this->getRouteByName($routeName);
        $reflection = $this->getMethodReflection($route);
        if (!$reflection){
            return false;
        }
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        $api_annotations = array_filter($annotations, function($item) {
            return is_subclass_of($item, 'Akuehnis\SymfonyApi\Annotations\Tag');
        });
        return 0 < count($api_annotations);
    }

    public function getParameterConverter($route, $name){
        $annotationReader = new AnnotationReader();
        $reflection = $this->getMethodReflection($route);
        if (!$reflection){
            return null;
        }
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        $converter = null;
        $parameter = null;
        $defaultValue = null;
        foreach ($reflection->getParameters() as $param){
            if ($param->getName() == $name){
                $parameter = $param;
                break;
            }
        }
        if (!$parameter){
            return null;
        }
        $reflection_type = $parameter->getType();
        $type = $reflection_type->getName();
        if ($parameter->isDefaultValueAvailable()){
            $defaultValue = $parameter->getDefaultValue();
        }
        $converter_annotations = array_filter($annotations, function($item) use ($name) {
            return is_subclass_of($item, 'Akuehnis\SymfonyApi\Converter\ApiConverter')
            && $item->property_name == $name;
        });
        $converter = array_shift($converter_annotations);
        if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
            // even if converter is found in annotations, converters passed as default value must have priority
            $converter = $defaultValue;
        } 
        if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
            $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            if ($parameter->isDefaultValueAvailable()){
                $converter = new $className(['defaultValue' => $parameter->getDefaultValue()]);
            } else {
                $converter = new $className([]);
            }
        }

        return $converter;
    }

}