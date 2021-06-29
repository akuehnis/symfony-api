<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

class DocBuilder
{

    protected $router;
    protected $config_documentation = [];

    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(UrlGeneratorInterface $UrlGeneratorInterface, $config_documentation)
    {
        $this->router = $UrlGeneratorInterface;
        $this->config_documentation = $config_documentation;
    }

    public function getDefinitions($rows){
        $definitions = [];
        foreach ($rows as $row){
            foreach ($row['args'] as $arg) {
                if ('body' == $arg['location']){
                    $reflect = new \ReflectionClass($arg['type']);
                    if (isset($definitions[$reflect->getShortName()])){
                        continue;
                    }
                    $definitions[$reflect->getShortName()] = $this->getDefinitionOfClass($arg['type']);
                } 
            }
            if (is_subclass_of($row['returnType'], 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $reflect = new \ReflectionClass($row['returnType']);
                $definitions[$reflect->getShortName()] = $this->getDefinitionOfClass($row['returnType']);
            }
        }
        return $definitions;
    }


    public function getDefinitionOfClass($classname) {
        https://symfony.com/doc/current/components/property_info.html
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $accessExtractors = [$reflectionExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );
        $def = [
            'type' => 'object',
            'properties' => [],
        ];

        $properties = $propertyInfo->getProperties($classname);
        foreach ($properties as $key){
            $types = $propertyInfo->getTypes($classname, $key);
            $type = array_shift($types);
            if ($type) {
                if ('float' == $type->getBuiltinType()) {
                    $def['properties'][$key] = [
                        'type' => 'number',
                        'format' => 'float'
                    ];
                } else if ('int' == $type->getBuiltinType()) {
                    $def['properties'][$key] = [
                        'type' => 'number',
                        'format' => 'integer'
                    ];
                } else if ('bool' == $type->getBuiltinType()) {
                    $def['properties'][$key] = [
                        'type' => 'boolean',
                    ];
                } else if ('string' == $type->getBuiltinType()) {
                    $def['properties'][$key] = [
                        'type' => 'string',
                    ];
                } else if ('DateTime' == $type->getClassName()){
                    $def['properties'][$key] = [
                        'type' => 'string',
                        'format' => 'date-time'
                    ];
                }
            }
        }
        return $def;

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
                        $def = [
                            'name' => $arg['name'],
                            'description' => "todo",
                            'in' => $arg['location'],
                            'required' => !$arg['optional'],
                        ];
                        if ('body' == $arg['location']){
                            $reflect = new \ReflectionClass($arg['type']);
                            $def['schema']['$ref'] = $reflect->getShortName();
                        } else {
                            $def['type'] = $arg['type'];
                        }
                        if ($arg['has_default']){
                            $def['default'] = $arg['default'];
                        }

                        $paths[$path][$method]['parameters'][] = $def;
                    }
                    //var_dump($row['returnType']);
                    if (is_subclass_of($row['returnType'], 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                        $reflect = new \ReflectionClass($row['returnType']);
                        $paths[$path][$method]['responses']['200']['schema']['$ref'] = $reflect->getShortName();
                    } else {
                        $paths[$path][$method]['responses']['200']['schema']['type'] = 'string'; //irgendwelcher HTML content
                    }
                }
            }
        }

        return $paths;
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
                if ('Akuehnis\SymfonyApi\Annotations\Tag' == get_class($annotation)){
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

    public function getRows($routes) 
    {
        https://symfony.com/doc/current/components/property_info.html
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $accessExtractors = [$reflectionExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );

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
            $tags = [];
            foreach ($annotations as $annotation) {
                if ('Akuehnis\SymfonyApi\Annotations\Tag' == get_class($annotation)){
                    $tags[] = $annotation->name;
                }
            }
            if (0 == count($tags)) {
                continue;
            }
            $returnType = $reflection->getReturnType();
            $row['tags'] = $tags;
            $row['returnType'] = null === $returnType 
                ? null
                : $reflection->getReturnType()->getName();
            $parameters = $reflection->getParameters();
            $args = [];
            foreach ($parameters as $parameter){
                $location = 'query';
                if (false !== strpos($row['path'], '{'.$parameter->getName().'}')){
                    $location = 'path';
                }
                if (is_subclass_of($parameter->getType()->getName(), 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                    $location = 'body';
                }
                $args[] = [
                    'type' => $parameter->getType()->getName(),
                    'name' => $parameter->getName(),
                    'optional' => $parameter->isOptional(),
                    'has_default' => $parameter->isDefaultValueAvailable(),
                    'default' => $parameter->isDefaultValueAvailable()
                        ? $parameter->getDefaultValue()
                        : null,
                    'location' => $location,
                ]; 
            }
            $row['args'] = $args;
            $rows[] = $row;
        }

        return $rows;
    }

    public function getSpec() 
    {
        $routes = $this->getRoutes();
        $rows = $this->getRows($routes);
        $spec = $this->config_documentation;
        $spec['swagger'] = '2.0';
        $spec['tags'] = $this->getTags($rows);
        $spec['paths'] = $this->getPaths($rows);
        $spec['definitions'] = $this->getDefinitions($rows);

        return $spec;
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


}