<?php 
namespace Akuehnis\SymfonyApi\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;

use Akuehnis\SymfonyApi\Services\DocBuilder;

class DocumentationController 
{
    protected $DocBuilder;

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }

    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->DocBuilder->getSpec());
        /*
        $annotationReader = new AnnotationReader();
        $endpoints = [];
        $routes = $this->router->getRouteCollection();
        foreach ($routes->all() as $route){
            $row = [
                'path' => $route->getPath(),
                'schemes' => $route->getSchemes(),
                'methods' => $route->getMethods(),
                'options' => $route->getOptions(),
                'defaults' => $route->getDefaults(),
                'requirements' => $route->getRequirements(),
                'condition' => $route->getCondition(),
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
            $row['tag'] = $tag;
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
            $endpoints[] = $row;
        }
        $api = [
            'swagger' => "2.0",
            'title'=> 'API title - todo',
            'summary'=> 'API summary - todo',
        ];
        $paths = [];
        foreach ($endpoints as $row){
            $path = $row['path'];
            $methods = array_filter($row['methods'], function($method){
                $method = strtolower($method);
                if (!in_array($method, ['options'])) {
                    return true;
                }
                return false;
            });
            foreach ($methods as $method){
                if (!isset($paths[$path])){
                    $paths[$path] = [];
                }
                if (!isset($paths[$path][$method])){
                    $paths[$path][$method] = [
                        "description" => "todo",
                        "tags" => ["default"],
                    ];
                }
            }
            
        }
        $api['paths'] = $paths;
        return new JsonResponse($api);
        */
    }
}