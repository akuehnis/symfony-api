<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;

use Akuehnis\SymfonyApi\Services\RouteService;
use Akuehnis\SymfonyApi\Models\Response400;
use Akuehnis\SymfonyApi\Models\ParaModel;

class DocBuilder
{

    protected $router;
    protected $config_areas = [];
    protected $RouteService;

    public function __construct(
        UrlGeneratorInterface $UrlGeneratorInterface
        , RouteService $RouteService
        , $config_areas
    ){
        $this->router = $UrlGeneratorInterface;
        $this->RouteService = $RouteService;
        $this->config_areas = $config_areas;
    }


    public function getSpec($area = 'default') 
    {
        $routes = $this->getApiRoutes($area);
        $spec = isset($this->config_areas[$area]) && isset($this->config_areas[$area]['documentation']) 
            ? $this->config_areas[$area]['documentation']
            : ['info' => ['title' => 'No documentation for this area found']];
        $spec['openapi'] = '3.0.1';
        $spec['tags'] = $this->getTags($routes);
        $spec['paths'] = $this->getPaths($routes);
        $spec['components']['schemas'] = $this->getDefinitions($routes);

        return $spec;
    }

    public function getApiRoutes($area) {
        $annotationReader = new AnnotationReader();
        $routes = $this->router->getRouteCollection();
        $routes_of_interest = [];
        $path_patterns = isset($this->config_areas[$area]) && isset($this->config_areas[$area]['path_patterns'])
                ? $this->config_areas[$area]['path_patterns']
                : [];
        $name_patterns = isset($this->config_areas[$area]) && isset($this->config_areas[$area]['name_patterns'])
            ? $this->config_areas[$area]['name_patterns']
            : [];
        foreach ($routes as $name=>$route){
            if ($this->matchPath($route, $path_patterns)
            && $this->matchName($name, $name_patterns)
            ){
                $routes_of_interest[] = $route;
            }
        }
        return $routes_of_interest;
    }

    public function matchPath($route, $path_patterns): bool
    {
        // code from nelmio api docs bundle
        foreach ($path_patterns as $pathPattern) {
            if (preg_match('{'.$pathPattern.'}', $route->getPath())) {
                return true;
            }
        }

        return 0 === count($path_patterns);
    }

    public function matchName($name, $name_patterns): bool
    {
        // code from nelmio api docs bundle
        foreach ($name_patterns as $namePattern) {
            if (preg_match('{'.$namePattern.'}', $name)) {
                return true;
            }
        }

        return 0 === count($name_patterns);
    }

    public function getPaths($routes){
        $annotationReader = new AnnotationReader();
        $paths = [];
        foreach ($routes as $route) {
            $tags = [];
            $parameters = [];
            $responses = [];
            $path = $route->getPath();
            $methods = array_filter($route->getMethods(), function($method){
                $method = strtolower($method);
                if (in_array($method, ['options'])) {
                    return false;
                }
                return true;
            });
            if (0 == count($methods)){
                $methods[] = 'GET';
            }
            $tag_annotations = $this->RouteService->getRouteAnnotations($route, 'Akuehnis\SymfonyApi\Annotations\Tag');
            foreach ($tag_annotations as $annotation) {
                $tags[] = $annotation->name;
            }
            if (0 == count($tags)){
                $tags[] = 'default';
            }
            $param_converters = $this->RouteService->getRouteParamConverters($route);
            $request_body = null;
            foreach ($param_converters as $converter){
                if ('body' == $converter->getLocation()[0]){
                    if ($converter->getIsArray()) {
                        $request_body = [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/' . $converter->getClassNameShort(),
                                        ]
                                    ]
                                ]
                            ]
                        ];
                    } else {
                        $request_body = [
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/' . $converter->getClassNameShort(),
                                    ]
                                ]
                            ]
                        ];
                    }
                } else {
                    $parameter =  [
                        'name' => $converter->getName(),
                        'in' => $converter->getLocation()[0],
                        'description' => $converter->getDescription(),
                        'schema' => $converter->getSchema(),
                    ];
                    if ($converter->getRequired()){
                        $parameter['required'] = true;
                    }
                    $parameters[] = $parameter;
                }
            }
            foreach ($this->RouteService->getRouteResponseConverters($route) as $status=>$converter){
                $responses[$status] = [
                    'description'=> $converter->getDescription(),
                    'content' => [
                        'application/json' => [
                            'schema' => $converter->getSchema()
                        ]
                    ] 
                ];
            }
            
            $docblock = $this->RouteService->getRouteDocComment($route);
            list($summary, $description) = $this->getSummaryAndDescription($docblock);
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



    public function getDefinitions($routes){
        $definitions = [];
        $class_names = [];
        foreach ($routes as $route){
            $param_converters = $this->RouteService->getRouteParamConverters($route);
            foreach ($param_converters as $converter){
                if (get_class($converter) == 'Akuehnis\SymfonyApi\Converter\ObjectConverter' ||
                    is_subclass_of($converter, 'Akuehnis\SymfonyApi\Converter\ObjectConverter')){
                    $class_names = array_merge($class_names, $converter->getAllClassNames());
                }
                if ('body' == $converter->getLocation()[0]){
                    $definitions[$converter->getClassNameShort()] = $converter->getSchema();
                }
            }
            foreach ($this->RouteService->getRouteResponseConverters($route) as $converter){
                if (get_class($converter) == 'Akuehnis\SymfonyApi\Converter\ObjectConverter' ||
                    is_subclass_of($converter, 'Akuehnis\SymfonyApi\Converter\ObjectConverter')){
                    $class_names = array_merge($class_names, $converter->getAllClassNames());
                }
            }
        }
        $class_names = array_unique($class_names);
        foreach ($class_names as $class_name){
            $converter = new \Akuehnis\SymfonyApi\Converter\ObjectConverter(['class_name' => $class_name]);
            $definitions[$converter->getClassNameShort()] = $converter->getSchema();
        }
        return $definitions;
    }

    /**
     * returns tuple [summary, description]
     */
    public function getSummaryAndDescription(string $docblock) {
        if (!$docblock){
            $docblock = '';
        }
        $summary = '';
        $description = '';
        $a = explode("\n", $docblock);
        foreach ($a as $i => $row){
            $row = trim($row, ' */');
            $row = trim($row);
            if ('' == $row){
                continue;
            } else if (0 === strpos($row, '@')){
                continue;
            } else if ('' == $summary) {
                $summary = $row;
            } else {
                $description.= $row;
            }
        }
        return [$summary, $description];
    }

}