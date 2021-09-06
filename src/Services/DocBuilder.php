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
        $annotationReader = new AnnotationReader();
        $paths = [];
        foreach ($routes as $route) {
            $reflection = $this->RouteService->getMethodReflection($route);
            $annotations = $annotationReader->getMethodAnnotations($reflection);
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
            foreach($reflection->getParameters() as $param){
                $type = $param->getType();
                $name = $param->getName();
                if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                    // will be used below, see request_body
                    continue;
                }
                $location = ['query'];
                if (false !== strpos($route->getPath(), '{'.$name.'}')){
                    $location = ['path']; 
                } 
                $converter = $this->RouteService->getParameterConverter($route, $name);
                if (!$converter){
                    continue;
                }
                $parameter_def = [
                    'name' => $name,
                ];
                if ($converter->getTitle()){
                    $parameter_def['description'] = $converter->getTitle();
                }
                $parameter_def['in'] = $location;
                $parameter_def['schema'] = [
                    'type' => $converter->getType(),
                ];
                if ($converter->getFormat()){
                    $parameter_def['schema']['format'] = $converter->getFormat();

                }
            
                $parameters[] = $parameter_def;
            }
            $responses = [];

            $return_model = $this->getReturnModel($route);
            if ($return_model){
                $reflect = new \ReflectionClass($return_model);
                $responses['200'] = [
                    'description'=> 'return model',
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

            $request_body = null;
            $body_type = null;
            $reflection = $this->RouteService->getMethodReflection($route);
            foreach ($reflection->getParameters() as $param) {
                $reflection_type = $param->getType();
                $type = $reflection_type->getName();
                if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                    $body_type = $type;
                    break;
                }

            }
            if ($body_type){
                $reflect = new \ReflectionClass($body_type);
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
                $reflect = new \ReflectionClass($class_name);
                $definitions[$reflect->getShortName()] = $this->getDefinitionOfClass($class_name);
            }         
        }
        $definitions['Response400'] = $this->getDefinitionOfClass('Akuehnis\SymfonyApi\Models\Response400');
        $definitions['ErrorModel'] = $this->getDefinitionOfClass('Akuehnis\SymfonyApi\Models\ErrorModel');
        return $definitions;
    }

    /**
     * returns all model classes for a route
     * 
     * Searches
     * - Body Class
     * - Return model class (annotation)
     */
    public function getClasses($route){
        $classes = [];
        $reflection = $this->RouteService->getMethodReflection($route);
        foreach ($reflection->getParameters() as $param){
            $reflection_type = $param->getType();
            $type = $reflection_type->getName();
            if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $classes[] = $type;
            }
        }

        $reflection = $this->RouteService->getMethodReflection($route);
        if ($reflection){
            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getMethodAnnotations($reflection);
            foreach($annotations as $annotation){
                if ('Akuehnis\SymfonyApi\Annotations\ResponseModel' == get_class($annotation)){
                    $classes[] = $annotation->name;
                }
            }
        }

        return $classes;
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

    /**
     * Ab PHP 8 sind Union Return Types möglich, z.B. 
     * A|null, ?A oder A|B|null
     */
    public function getReturnModel($route)
    {
        $annotationReader = new AnnotationReader();
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return null;
        }
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        foreach ($annotations as $annotation){
            if (get_class($annotation) == 'Akuehnis\SymfonyApi\Annotations\ResponseModel'){
                return $annotation->name;
            }
        }
        return null;
    }

    
    /**
     * Returns openapi definition of a class name
     * 
     * @param string $classname Name of the class
     * @return mixed type object with properties
     */
    public function getDefinitionOfClass($classname) 
    {  
        $annotationReader = new AnnotationReader();
        $def = [
            'type' => 'object',
            'properties' => [],
        ];
        $reflection = new \ReflectionClass($classname);
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $reflection_type = $property->getType();
            if (!$reflection_type) {
                continue;
            }
            $type = $reflection_type->getName();
            $name = $property->getName();
            $converter = null;
            if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $obj->name = $this->getDefinitionOfClass($type);
            } else {
                $annotations = $annotationReader->getPropertyAnnotations($property);
                foreach ($annotations as $annotation){
                    if (is_object($annotation) && is_subclass_of($annotation, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                        $converter = $annotation;
                    }
                }
                if (!$converter){
                    if (in_array($type, ['bool', 'int', 'string', 'float', 'array'])){
                        $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                        if ($property->hasDefaultValue()){
                            $converter = new $className(['defaultValue' => $property->getDefaultValue()]);
                        } else {
                            $converter = new $className([]);
                        }
                    } else {
                        throw new \Exception("No converter found for " . $name);
                    }
                }
            }
            if ($converter){
                $openapi_prop = (object)[
                    'type' => $converter->getType(),
                ];
                if ($converter->getFormat()){
                    $openapi_prop->format = $converter->getFormat();
                }
                $def['properties'][$name] = $openapi_prop;
                /*
                 $reflect = new \ReflectionClass($prop_model->items->type);
                 $openapi_prop->items = [
                     '$ref' => '#/components/schemas/' . $reflect->getShortName(),
                    ];
                    
                */
            }

        }

        return $def;
    }

    

}