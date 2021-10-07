<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Doctrine\Common\Annotations\AnnotationReader;

use Akuehnis\SymfonyApi\Services\RouteService;

class QueryResolver implements ArgumentValueResolverInterface
{
    protected $RouteService;

    protected $converters = [];
    protected $converter_idx = [];

    public function __construct(RouteService $RouteService)
    {
        $this->RouteService = $RouteService;
    }

    /**
     * check if the parameter type is supported
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $name = $argument->getName();
        if (0 == count($this->converters)){
            $route = $this->RouteService->getRouteFromRequest($request);
            if (!$route){
                return false;
            }
            $this->converters = $this->RouteService->getRouteParamConverters($route);
        }
        if (0 == count($this->converters)){
            return false;
        } else {
            $filtered_converters = array_filter($this->converters, function($c) use ($name){
                return $name == $c->getName();
            });
            return 0 < count($filtered_converters);
        }
    }

    /**
     * Resolves the controller method parameters
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $name = $argument->getName();
        $query_values = $request->query->all();
        $path_values = $request->attributes->all();
        $filtered_converters = array_filter($this->converters, function($c) use ($name){
            return $name == $c->getName();
        });
        $converter = array_shift($filtered_converters);
        $location = $converter->getLocation();
        if ('query' == $location[0]) {
            if (isset($query_values[$name])){
                yield $converter->denormalize($query_values[$name]);
            } else {
                yield $converter->denormalize($converter->getValue());
            }
        } else if ('path' == $location[0] && isset($path_values[$name])){
            yield $converter->denormalize($path_values[$name]);
        } else if ('body' == $location[0]){
            $data = json_decode($request->getContent(), true);
            yield $converter->denormalize($data);
        }
        
    }
}