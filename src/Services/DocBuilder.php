<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use Akuehnis\SymfonyApi\Services\RouteService;
use Akuehnis\SymfonyApi\Models\Response400;
use Akuehnis\SymfonyApi\Models\ParaModel;

class DocBuilder
{

    protected $router;
    protected $config_documentation = [];
    protected $RouteService;

    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(
        UrlGeneratorInterface $UrlGeneratorInterface
        , RouteService $RouteService
        , $config_documentation
    ){
        $this->router = $UrlGeneratorInterface;
        $this->RouteService = $RouteService;
        $this->config_documentation = $config_documentation;

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

    public function getApiRoutes() {
        $annotationReader = new AnnotationReader();
        $routes = $this->router->getRouteCollection();
        $routes_of_interest = [];
        foreach ($routes as $route){         
            $reflection = $this->RouteService->getMethodReflection($route);
            if (!$reflection) {
                continue;
            }
            $annotations = $annotationReader->getMethodAnnotations($reflection );
            $tag = null;
            foreach ($annotations as $annotation) {
                if ('Akuehnis\SymfonyApi\Annotations\Tag' == get_class($annotation)){
                    $routes_of_interest[] = $route;
                    break;
                }
            }
        }
        return $routes_of_interest;
    }

    public function getRouteByName($name){
        return $this->router->getRouteCollection()->get($name);
    }

    public function getPaths($routes){
        $paths = [];
        foreach ($routes as $route) {
            $reflection = $this->RouteService->getMethodReflection($route);
            $docComment = $reflection  ? $reflection->getDocComment() : null;
            $factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
            $docblock = $factory->create($docComment ? $docComment : '/** */');
            $path = $route->getPath();
            $tags = $this->getMethodTags($route);
            $summary = $docblock ? $docblock->getSummary() : '';
            $description = $docblock ? $docblock->getDescription()->getBodyTemplate() : '';
            $methods = array_filter($route->getMethods(), function($method){
                $method = strtolower($method);
                if (in_array($method, ['options'])) {
                    return false;
                }
                return true;
            });

            $parameters = [];
            foreach ($this->getRouteParameterModels($route) as $param_name => $parameter_model) {
                if ('body' == $parameter_model->location){
                    // will be used below, see request_body
                    continue;
                }
                $parameter_def = [
                    'name' => $param_name,
                ];
                if ($parameter_model->description){
                    $parameter_def['description'] = $parameter_model->description;
                }
                if (null !== $parameter_model->location){
                    $parameter_def['in'] = $parameter_model->location;
                }
                if (true === $parameter_model->required){
                    $parameter_def['required'] = true;
                }
                
                
                if ($parameter_model->type){   
                    list($type, $format) = $this->getTypeAndFormat($parameter_model->type);
                    $parameter_def['schema'] = [
                        'type' => $type
                    ];
                    if (null !== $format) {
                        $parameter_def['schema']['format'] = $format;
                    }
                    if ($parameter_model->has_default){
                        $parameter_def['schema']['default'] = $parameter_model->default;
                    }
                    if ($parameter_model->is_nullable){
                        $parameter_def['schema']['nullable'] = true;
                    }

                }
                $parameters[] = $parameter_def;
            }
            $responses = [];

            $return_model = $this->getReturnModel($route);
            if (null !== $return_model){
                if ('array' == $return_model->type){
                    $responses['200'] = [
                        'description'=> $return_model->description,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'array',
                                ]
                            ]
                        ]
                    ];
                    if (null === $return_model) {
                        // nothing to add. shall we throw an exception?
                    } else if (in_array($return_model->items->type, ['bool', 'float', 'string', 'int'])){
                        list($type, $format) = $this->getTypeAndFormat($return_model->items->type);
                        $responses['200']['content']['application/json']['schema']['items']['type'] = $type;
                        if ($format){
                            $responses['200']['content']['application/json']['schema']['items']['format'] = $format;
                        }
                    } else {
                        $reflect = new \ReflectionClass($return_model->items->type);
                        $responses['200'] = [
                            'description'=> $return_model->description,
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            'schema' => [
                                                '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                                                ]
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    }
                } else if (method_exists($return_model->type, 'symfonyApiStoreSubmittedData')){
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
                } else if ($return_model->type == 'Symfony\Component\HttpFoundation\JsonResponse'){
                    $reflect = new \ReflectionClass($return_model->type);
                    $responses['200'] = [
                        'description'=> $return_model->description,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
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
            $responses['400'] = [
                'description'=> 'Validation errors',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            '$ref' => '#/components/schemas/Response400',
                        ]
                    ]
                ],
            ];

            $body_model = null;
            $request_body = null;
            foreach ($this->getRouteParameterModels($route) as $param_name => $param_model) {
                if ('body' == $param_model->location){
                    $body_model = $param_model;
                }
            }
            if ($body_model){
                $reflect = new \ReflectionClass($body_model->type);
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

    public function getTags($routes){
        $tags = [];
        foreach ($routes as $route){
            $reflection = $this->RouteService->getMethodReflection($route);
            if (null === $reflection){
                continue;
            }
            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getMethodAnnotations($reflection);
            foreach ($annotations as $annotation) {
                if ('Akuehnis\SymfonyApi\Annotations\Tag' == get_class($annotation)){
                    $tags[] = $annotation->name;
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

    public function getMethodTags($route) {
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return null;
        }
        $annotationReader = new AnnotationReader();
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        $tags = [];
        foreach ($annotations as $annotation) {
            if ('Akuehnis\SymfonyApi\Annotations\Tag' == get_class($annotation)){
                $tags[] = $annotation->name;
            }
        }

        return $tags;
    }

    public function getDefinitions($routes){
        $definitions = [];
        foreach ($routes as $route){
            $classes = $this->getClasses($route);
            foreach($classes as $class_name){
                if (method_exists($class_name, 'symfonyApiStoreSubmittedData')){
                    $reflect = new \ReflectionClass($class_name);
                    $definitions[$reflect->getShortName()] = $this->getDefinitionOfClass($class_name);
                }
            }         
        }
        $definitions['Response400'] = $this->getDefinitionOfClass('Akuehnis\SymfonyApi\Models\Response400');
        $definitions['ErrorModel'] = $this->getDefinitionOfClass('Akuehnis\SymfonyApi\Models\ErrorModel');
        return $definitions;
    }

    public function getClasses($route){
        $parameters = $this->getRouteParameterModels($route);
        $returnModel = $this->getReturnModel($route);
        $models = [];
        foreach ($parameters as $name => $param){
            $type = $param->type;
            if ('array' == $type){
                $type = $param->items->type;
            }
            if (!in_array($type, ['bool', 'int', 'string', 'float'])){
                $models[$name] = $type;
            }
        }
        if ($returnModel){
            $type = $returnModel->type;
            if ('array' == $type && $returnModel->items){
                $type = $returnModel->items->type;
            } 
            if (!in_array($type, ['bool', 'int', 'string', 'float', 'array'])){
                $models['responseModel'] = $type;
            }

        }

        return $models;
    }

    /**
     * Collects models from Typehinting and Docblock and merges
     * 
     * @return ParamModel[] Merged Models
     */
    public function getRouteParameterModels($route){
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return [];
        }
        $docComment = $reflection->getDocComment();
        $docblock = null;
        if ($docComment){
            $factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
            $docblock = $factory->create($docComment);
        }
        $models = [];
        foreach ($reflection->getParameters() as $parameter){

            $reflection_type = $parameter->getType();
            if (!$reflection_type) {
                continue;
            }
            $name = $parameter->getName();
            $model = new ParaModel();
            $model->name = $name;
            $model->type = $reflection_type->getName();
            if ('array' ==  $model->type){
                $model->items = new ParaModel();
            }
            if (!in_array($model->type, ['int', 'bool', 'float', 'string', 'array']) && !method_exists($model->type, 'symfonyApiStoreSubmittedData')){
                // Don't use it
                continue;
            }
            $model->location = 'query';
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $model->location = 'path';
            } else if (method_exists($reflection_type->getName(), 'symfonyApiStoreSubmittedData')){
                $model->location = 'body';
            }
            
            $model->required = !$parameter->isOptional();
            $model->has_default = $parameter->isDefaultValueAvailable();
            $model->default = $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : false;
            $model->is_nullable = $reflection_type->allowsNull();
            
            if ($docblock){
                $model->description = $docblock->getSummary();
                foreach($docblock->getTags() as $tag){
                    if ('param' == $tag->getName() && $name == $tag->getVariableName()){
                        if ($tag->getDescription()->getBodyTemplate()){
                            // If variable description available, use it
                            $model->description = $tag->getDescription()->getBodyTemplate();
                        }
                        $tagType = $tag->getType();
                        if ('phpDocumentor\Reflection\Types\Array_' == get_class($tagType)){
                            $model->type = 'array';
                            $model->items = new ParaModel();
                            $valueType = $tagType->getValueType();
                            if ('phpDocumentor\Reflection\Types\Object_' == get_class($valueType)){
                                $model->items->type = $valueType->getFqsen()->__toString();
                            } else {
                                $model->items->type = $valueType->__toString();
                            }
                        } else if (null === $model->type) {
                            $model->type = $tagType->__toString();
                        }
                    }
                }
            }

            $models[$name] = $model;
        }
        return $models;
    }

    /**
     * Collects properties from Typehinting and Docblock and merges
     * 
     * The models will be used for validation purposes. For Openapi see getDefinitionOfClass method.
     * 
     * @return ParamModel[] Merged Models
     */
    public function getClassPropertyModels($classname){
        
        $reflection = new \ReflectionClass($classname);
        $instance = new $classname();
        $models = [];
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $model = new ParaModel();
            $name = $property->getName();
            $reflection_named_type = $property->getType();
            $model->name = $name;
            $model->location = $classname;
            $model->type = $reflection_named_type ? $reflection_named_type->getName() : null;
            $model->required = !$property->isInitialized($instance);
            // ReflectionProperty::hasDefaultValue requires PHP 8
            $model->has_default = $property->isInitialized($instance); 
            //ReflectionProperty::getDefaultValue requires PHP 8
            $model->default = $property->isInitialized($instance) ? $property->getValue($instance) : false; 
            $model->is_nullable = $reflection_named_type ? $reflection_named_type->allowsNull() : false;
            $docComment = $property->getDocComment();
            if ($docComment){
                // read doccomment
                $factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
                $docblock = $factory->create($docComment);
                if ($docblock){
                    $model->description = $docblock->getSummary();
                    foreach($docblock->getTags() as $tag){
                        if ('var' == $tag->getName()){
                            if ($tag->getDescription()->getBodyTemplate()){
                                // If variable description available, use it
                                $model->description = $tag->getDescription()->getBodyTemplate();
                            }
                            $tagType = $tag->getType();
                            if ('phpDocumentor\Reflection\Types\Array_' == get_class($tagType)){
                                $model->type = 'array';
                                $model->items = new ParaModel();
                                $valueType = $tagType->getValueType();
                                if ('phpDocumentor\Reflection\Types\Object_' == get_class($valueType)){
                                    $model->items->type = $valueType->getFqsen()->__toString();
                                } else {
                                    $model->items->type = $valueType->__toString();
                                }
                            } else if (null === $model->type) {
                                $model->type = $tagType->__toString();
                            }
                        }
                    }
                }
            }
            $models[$name] = $model;
        }    
        return $models;
    }

    public function getReturnModel($route)
    {
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return null;
        }
        $returnType = $reflection->getReturnType();
        $model = new ParaModel();
        $model->type = null === $returnType ? 'string' : $reflection->getReturnType()->getName();
        if ('array' == $model->type){
            $model->items = new ParaModel();
        }
        $docComment = $reflection->getDocComment();
        $docblock = null;
        if ($docComment){
            $factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
            $docblock = $factory->create($docComment);
        }
        if ($docblock){
            foreach($docblock->getTags() as $tag){
                if ('return' == $tag->getName()){
                    $model->description = $tag->getDescription()->getBodyTemplate();
                    $tagType = $tag->getType();
                    if ('phpDocumentor\Reflection\Types\Array_' == get_class($tagType)){
                        $model->type = 'array';
                        $model->items = new ParaModel();
                        $valueType = $tagType->getValueType();
                        if ('phpDocumentor\Reflection\Types\Object_' == get_class($valueType)){
                            $model->items->type = $valueType->getValueType()->getFqsen()->getName();
                        } else {
                            $model->items->type = $valueType->__toString();
                        }
                    } else {
                        $model->type = $tagType->__toString();
                    }
                }
            }
        }
        
        return $model;
    }

    
    /**
     * Returns openapi definition of a class name
     * 
     * @param string $classname Name of the class
     * @return mixed type object with properties
     */
    public function getDefinitionOfClass($classname) 
    {  
        $def = [
            'type' => 'object',
            'properties' => [],
        ];
        foreach ($this->getClassPropertyModels($classname) as $name=>$prop_model){
            // do not touche original model used by request validator
            list($type, $format) = $this->getTypeAndFormat($prop_model->type);
            $openapi_prop = (object)[
                'type' => $type,
            ];
            if ($format){
                $openapi_prop->format = $format;
            }
            if ($prop_model->description){
                $openapi_prop->description = $prop_model->description;
            }
            if ($prop_model->is_nullable){
                $openapi_prop->nullable = true;
            }
            if ($prop_model->has_default){
                $openapi_prop->default = $prop_model->default;
            }
            if ('array' == $type){
                if (is_object($prop_model->items) && null !== $prop_model->items->type){
                    if (in_array($prop_model->items->type, ['bool', 'float', 'string', 'int'])){
                        list($item_type, $item_format) = $this->getTypeAndFormat($prop_model->items->type);
                        $openapi_prop->items = [];
                        $openapi_prop->items['type'] = $item_type;
                        if (null !== $item_format){
                            $openapi_prop->items['format'] = $item_format;
                        }
                    } else {
                        $reflect = new \ReflectionClass($prop_model->items->type);
                        $openapi_prop->items = [
                            '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                        ];
                    }
                } else {
                    $openapi_prop->items = (object)[]; // Any type
                }
            }
            $def['properties'][$name] = $openapi_prop;
        }

        return $def;
    }

    /**
     * Convert PHP type to openapi type and format
     * 
     * @param string $type
     * @return mixed 
     */
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
        } else if ('string' == $type){
            $type = 'string';
        } else if ('mixed' == $type){
            $type = (object)[]; // Any Type
        }

        return [$type, $format];
    }

}