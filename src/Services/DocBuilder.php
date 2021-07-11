<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use Akuehnis\SymfonyApi\Services\DocBlockService;
use Akuehnis\SymfonyApi\Services\TypeHintService;
use Akuehnis\SymfonyApi\Services\RouteService;
use Akuehnis\SymfonyApi\Models\Response400;

class DocBuilder
{

    protected $router;
    protected $config_documentation = [];
    protected $DocBlockService;
    protected $TypeHintService;
    protected $RouteService;

    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(
        UrlGeneratorInterface $UrlGeneratorInterface
        , DocBlockService $DocBlockService
        , TypeHintService $TypeHintService
        , RouteService $RouteService
        , $config_documentation
    ){
        $this->router = $UrlGeneratorInterface;
        $this->DocBlockService = $DocBlockService;
        $this->TypeHintService = $TypeHintService;
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
            $path = $route->getPath();
            $tags = $this->TypeHintService->getMethodTags($route);
            $summary = $this->DocBlockService->getMethodSummary($route);
            $description = $this->DocBlockService->getMethodDescription($route);
            $methods = array_filter($route->getMethods(), function($method){
                $method = strtolower($method);
                if (in_array($method, ['options'])) {
                    return false;
                }
                return true;
            });

            $parameters = [];
            foreach ($this->getParameterModels($route) as $param_name => $parameter_model) {
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
                } else if (is_subclass_of($return_model->type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
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
            foreach ($this->getParameterModels($route) as $param_name => $param_model) {
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

    public function getDefinitions($routes){
        $definitions = [];
        foreach ($routes as $route){
            $classes = $this->DocBlockService->getClasses($route);
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
        $definitions['Response400'] = $this->getDefinitionOfClass('Akuehnis\SymfonyApi\Models\Response400');
        return $definitions;
    }

    /**
     * Collects models from Typehinting and Docblock and merges
     * 
     * @return ParamModel[] Merged Models
     */
    public function getParameterModels($route){
        $params_typehint =  $this->TypeHintService->getParameterModels($route);
        $params_docblock =  $this->DocBlockService->getParameterModels($route);
        foreach ($params_typehint as $name => $model){
            if (!isset($params_docblock[$name])){
                continue;
            }
            $params_typehint[$name]->description = $params_docblock[$name]->description;
            if ('array' == $model->type) {
                $params_typehint[$name]->items = $params_docblock[$name]->items;
            }
        }
        return $params_typehint;
    }

    public function getReturnModel($route)
    {
        $return_docblock = $this->DocBlockService->getMethodReturnModel($route);
        $return_typehint = $this->TypeHintService->getMethodReturnModel($route);
        if ($return_docblock){
            $return_typehint->description = $return_docblock->description;
            if ('array' == $return_typehint->type){
                $return_typehint->items = $return_docblock->items;
            }
        }
        return $return_typehint;
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
                } else if ('array' == $type->getBuiltinType()){
                    // Todo: noch nicht korrekt. additionalProperties, siehe hier
                    // https://swagger.io/docs/specification/data-models/dictionaries/
                    $def['properties'][$key] = [
                        'type' => 'array',
                        'items' => [
                            'type' => 'string'
                        ]
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

}