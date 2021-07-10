<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use Akuehnis\SymfonyApi\Services\DocBlockService;
use Akuehnis\SymfonyApi\Services\TypeHintService;

class DocBuilder
{

    protected $router;
    protected $config_documentation = [];
    protected $DocBlockService;
    protected $TypeHintService;

    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(
        UrlGeneratorInterface $UrlGeneratorInterface
        , DocBlockService $DocBlockService
        , TypeHintService $TypeHintService
        , $config_documentation
    ){
        $this->router = $UrlGeneratorInterface;
        $this->DocBlockService = $DocBlockService;
        $this->TypeHintService = $TypeHintService;
        $this->config_documentation = $config_documentation;

    }

    public function getDefinitions($routes){
        $definitions = [];
        foreach ($routes as $route){
            $reflection = $this->TypeHintService->getMethodReflection($route);
            $docComment = $reflection->getDocComment();
            $classes = $this->DocBlockService->getClasses($docComment);
            $classes_docblock = $this->TypeHintService->getClasses($route);
            foreach($classes_docblock as $name => $class_name){
                $classes[$name] = $class_name;
            }
            foreach($classes as $class_name){
                if (is_subclass_of($class_name, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                    $reflect = new \ReflectionClass($class_name);
                    $definitions[$reflect->getShortName()] = $this->getDefinitionOfClass($class_name);
                }
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
            if (null === $types){
                continue;
                // Todo: if any of Response, JsonResponse or similar, create it's definition
            }
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

    public function getTypeAndFormat($type){
        $type = $type;
        $format = null;
        if ('int' == $type){
            $type = 'integer';
        } else if ('float' == $type){
            $type = 'number';
            $format = 'float';
        } else if ('bool' == $type){
            $type = 'boolean';
        }

        return [$type, $format];
    }

    public function getPaths($routes){
        $paths = [];
        foreach ($routes as $route) {
            $reflection = $this->TypeHintService->getMethodReflection($route);
            $docComment = $reflection->getDocComment();
            $path = $route->getPath();
            $tags = $this->TypeHintService->getMethodTags($route);
            $summary = $this->DocBlockService->getMethodSummary($docComment);
            $description = $this->DocBlockService->getMethodDescription($docComment);
            $params_docblock =  $this->DocBlockService->getParameters($docComment);
            $params_typehint =  $this->TypeHintService->getParameters($route);
            $methods = array_filter($route->getMethods(), function($method){
                $method = strtolower($method);
                if (in_array($method, ['options'])) {
                    return false;
                }
                return true;
            });

            $parameters = [];
            foreach ($params_typehint as $param_name => $def) {
                if ('body' == $def->location){
                    // will be used below, see request_body
                    continue;
                }
                $parameter_def = [
                    'name' => $param_name,
                ];
                if (isset($params_docblock[$param_name]) && $params_docblock[$param_name]->description){
                    $parameter_def['description'] = $params_docblock[$param_name]->description;
                }
                if (null !== $def->location){
                    $parameter_def['in'] = $def->location;
                }
                if (true === $def->required){
                    $parameter_def['required'] = true;
                }
                
                
                if (isset($params_docblock[$param_name]) && $params_docblock[$param_name]->type){   
                    // Wenn docblock vorhande, verwende diese Definition 
                    list($type, $format) = $this->getTypeAndFormat($params_docblock[$param_name]->type);
                    $parameter_def['schema'] = [
                        'type' => $type
                    ];
                    if (null !== $format) {
                        $parameter_def['schema']['format'] = $format;
                    }
                    if (null !== $def->has_default){
                        $parameter_def['schema']['default'] = $def->default;
                    }

                } else if ($def->type) {
                    // Wenn kein Docblock vorhanden, verwende Typehints
                    list($type, $format) = $this->getTypeAndFormat($def->type);
                    $parameter_def['schema'] = [
                        'type' => $type
                    ];
                    if (null !== $format) {
                        $parameter_def['schema']['format'] = $format;
                    }
                    if (null !== $def->has_default){
                        $parameter_def['schema']['default'] = $def->default;
                    }
                }

                $parameters[] = $parameter_def;
            }
            $responses = [];

            $return_docblock = $this->TypeHintService->getMethodReturnModel($route);
            $return_typehint = $this->DocBlockService->getMethodReturnModel($docComment);
            $return_model = null !== $return_docblock
                ? $return_docblock
                : $return_typehint;
            if (null !== $return_model){
                if (is_subclass_of($return_model->type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                    $reflect = new \ReflectionClass($return_model->type);
                    $responses['200'] = [
                        'description'=> $return_model->description,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                                ]
                            ]
                        ]
                    ];
                } else {
                    $responses['200'] = [
                        'description'=> $return_model->description,
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

            $body_param_name = null;
            foreach ($params_typehint as $param_name => $def) {
                if ('body' == $def->location){
                    $body_param_name = $param_name;
                }
            }
            $body_model = null;
            $request_body = null;
            if ($body_param_name && isset($params_docblock[$body_param_name])){
                $body_model = $params_docblock[$body_param_name];
            } else if ($body_param_name && isset($params_typehint[$body_param_name])){
                $body_model = $params_typehint[$body_param_name];
            }
            if ($body_model){
                $reflect = new \ReflectionClass($def->type);
                $request_body = [
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                            ]
                        ]
                    ]
                ];
            }

            foreach ($methods as $method){
                $method = strtolower($method);
                if (!isset($paths[$path])){
                    $paths[$path] = [];
                }
                $paths[$path][$method] = [
                    'summary' => $summary,
                    "description" => $description,
                    "tags" => $tags,
                    'parameters' => $parameters,
                    'responses' => $responses,
                ];
                if ($request_body){
                    $paths[$path][$method]['requestBody'] = $request_body;
                }
            }
        }

        return $paths;
    }

    public function getApiRoutes() {
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


    public function getSpec() 
    {
        $routes = $this->getApiRoutes();
        $spec = $this->config_documentation;
        $spec['openapi'] = '3.0.1';
        $spec['tags'] = $this->getTags($routes);
        $spec['paths'] = $this->getPaths($routes);
        $spec['components']['schemas'] = $this->getDefinitions($routes);

        return $spec;
    }

    public function getTags($routes){

        $tags = [];
        foreach ($routes as $route){
            $route_tags = $this->TypeHintService->getMethodTags($route);
            foreach ($route_tags as $tag){
                $tags[] = $tag;
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