<?php

namespace Akuehnis\SymfonyApi\Services;


class RouteService
{
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
}