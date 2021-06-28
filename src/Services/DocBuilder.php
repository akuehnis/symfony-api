<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;


class DocBuilder
{

    protected $router;

    public function __construct(UrlGeneratorInterface $UrlGeneratorInterface)
    {
        $this->router = $UrlGeneratorInterface;
    }

    public function getSpec() 
    {
        $routes = $this->getRoutes();
        $rows = $this->getEndpoints($routes);
        return [
            'swagger' => "2.0",
            'info' => [
                "Title" => "Todo",
            ],
            'host' => "petstore.swagger.io",
            'schemes' => ["http", "https"],
            'tags' => $this->getTags($rows),
            'paths' => $this->getPaths($rows),
            'definitions' => $this->getDefinitions($rows),
        ];
    }

    public function getRoutes() {
        $annotationReader = new AnnotationReader();
        $routes = $this->router->getRouteCollection();
        $routes_of_interest = [];
        foreach ($routes as $route){         
            $defaults =  $route->getDefaults();  
            if (!isset($defaults['_controller']) || false === strpos($defaults['_controller'], '::')){
                continue;
            }
            list($class, $method) = explode('::', $defaults['_controller']);
            if (!class_exists($class)){
                continue;
            }
            $reflection = new \ReflectionMethod($class, $method);
            $annotations = $annotationReader->getMethodAnnotations($reflection );
            $tag = null;
            foreach ($annotations as $annotation) {
                if ('OpenApi\Annotations\Tag' == get_class($annotation)){
                    $tag = $annotation->name;
                }
            }
            if (!$tag) {
                continue;
            }
            $routes_of_interest[] = $route;
        }
        return $routes_of_interest;
    }

    public function getEndpoints($routes) 
    {
        $annotationReader = new AnnotationReader();
        $rows = [];
        foreach ($routes as $route){
            $row = [
                'path' => $route->getPath(),
                'schemes' => $route->getSchemes(),
                'methods' => $route->getMethods(),
                'options' => $route->getOptions(),
                'defaults' => $route->getDefaults(),
                'requirements' => $route->getRequirements(),
                'condition' => $route->getCondition(),
                'tags' => ['default'],
            ];
            
            if (!isset($row['defaults']['_controller']) || false === strpos($row['defaults']['_controller'], '::')){
                continue;
            }
            list($class, $method) = explode('::', $row['defaults']['_controller']);

            if (!class_exists($class)){
                continue;
            }
            $reflection = new \ReflectionMethod($class, $method);
            $annotations = $annotationReader->getMethodAnnotations($reflection );
            $tag = null;
            foreach ($annotations as $annotation) {
                if ('OpenApi\Annotations\Tag' == get_class($annotation)){
                    $tag = $annotation->name;
                }
            }
            if (!$tag) {
                continue;
            }
            $returnType = $reflection->getReturnType();
            $row['tags'] = [$tag];
            $row['returnType'] = null === $returnType 
                ? null
                : $reflection->getReturnType()->getName();
            $parameters = $reflection->getParameters();
            $args = [];
            foreach ($parameters as $parameter){
                $row['args'][] = [
                    'type' => $parameter->getType()->getName(),
                    'name' => $parameter->getName(),
                    'optional' => $parameter->isOptional(),
                    'has_default' => $parameter->isDefaultValueAvailable(),
                    'default' => $parameter->isDefaultValueAvailable()
                        ? $parameter->getDefaultValue()
                        : null,
                ]; 
            }
            $rows[] = $row;
        }

        return $rows;
    }

    public function getPaths($rows){
        $paths = [];
        foreach ($rows as $row) {
            $path = $row['path'];
            $methods = array_filter($row['methods'], function($method){
                $method = strtolower($method);
                if (!in_array($method, ['options'])) {
                    return true;
                }
                return false;
            });
            foreach ($methods as $method){
                $method = strtolower($method);
                if (!isset($paths[$path])){
                    $paths[$path] = [];
                }
                if (!isset($paths[$path][$method])){
                    $paths[$path][$method] = [
                        'summary' => "todo",
                        "description" => "todo",
                        "tags" => $row['tags'],
                        'parameters' => [],
                    ];
                    foreach ($row['args'] as $arg) {
                        $paths[$path][$method]['parameters'][] = [
                            'name' => $arg['name'],
                            'type' => $arg['type'],
                            'description' => "todo",
                            'in' => 'path',
                        ];
                    }
                }
            }
        }

        return $paths;
    }

    public function getTags($rows){
        $tags = [];
        foreach ($rows as $row){
            if (isset($row['tags'])){
                foreach ($row['tags'] as $tag){
                    $tags[] = $tag;
                }
            }
        }
        $tags = array_unique($tags);

        $out = [];
        foreach ($tags as $tag){
            $out[] = [
                'name' => $tag,
            ];
        }
        return $out;
    }

    public function getDefinitions($rows){
        return []; //todo
    }
}