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
                        'summary' => $row['summary'],
                        "description" => $row['description'],
                        "tags" => $row['tags'],
                        'parameters' => [],
                    ];
                    foreach ($row['args'] as $arg) {
                        if ('body' == $arg['location']){
                            $reflect = new \ReflectionClass($arg['type']);
                            $paths[$path][$method]['requestBody']['content']['application/json']['schema']['$ref'] = '#/components/schemas/' . $reflect->getShortName();
                        } else {
                            $parameter = [
                                'name' => $arg['name'],
                                'description' => $arg['description'],
                                'in' => $arg['location'],
                                'required' => !$arg['has_default'],
                            ];
                            
                            $schema = [];
                            if ('string' == $arg['type']){
                                $schema['type'] = 'string';
                            } else if ('int' == $arg['type']){
                                $schema['type'] = 'integer';
                            } else if ('float' == $arg['type']){
                                $schema['type'] = 'number';
                                $schema['format'] = 'float';
                            } else if ('bool' == $arg['type']){
                                $schema['type'] = 'boolean';
                            }
                            if ($arg['has_default']){
                                $schema['default'] = $arg['default'];
                            }
                            $parameter['schema'] = $schema;
                            $paths[$path][$method]['parameters'][] = $parameter;
                        }

                    }
                    if (is_subclass_of($row['returnType'], 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                        $reflect = new \ReflectionClass($row['returnType']);
                        $paths[$path][$method]['responses']['200'] = [
                            'description'=> $row['response_description'],
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $paths[$path][$method]['responses']['200'] = [
                            'description'=> $row['response_description'],
                            'content' => [
                                'text/html' => [
                                    'schema' => [
                                        'type' => 'string'
                                    ]
                                ]
                            ],
                        ];
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

            $docblock = $reflection->getDocComment();
            list($summary, $description, $arguments, $response_description) = $this->getSummaryAndDescription($docblock);
            $row['summary'] = $summary;
            $row['description'] = $description;
            $row['response_description'] = $response_description;
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
                    'description' => isset($arguments[$parameter->getName()]) ? $arguments[$parameter->getName()] : '',
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
        $spec['openapi'] = '3.0.1';
        $spec['tags'] = $this->getTags($rows);
        $spec['paths'] = $this->getPaths($rows);
        $spec['components']['schemas'] = $this->getDefinitions($rows);

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

    public function getSummaryAndDescription(string $docblock) {
        if (!$docblock){
            $docblock = '';
        }
        $summary = '';
        $description = '';
        $parameters = [];
        $return = '';
        $a = explode("\n", $docblock);
        foreach ($a as $i => $row){
            $row = trim($row, ' */');
            $row = trim($row);
            if ('' == $row){
                continue;
            } else if (0 === strpos($row, '@param')){
                $start = strpos($row, '$');
                
                if ($start){
                    $string = substr($row, $start + 1);
                    $end = strpos($string, ' ');
                    $parameter_name = substr($string, 0, $end);
                    $parameter_description = substr($string, $end+1);
                    $parameters[$parameter_name] = $parameter_description;
                }
            } else if (0 === strpos($row, '@return')){
                $return = trim(str_replace('@return', '', $row));       
            } else if (0 === strpos($row, '@')){
                continue;
            } else if ('' == $summary) {
                $summary = $row;
            } else {
                $description.= $row;
            }
        }
        return [$summary, $description, $parameters, $return];
    }


}