<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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
}