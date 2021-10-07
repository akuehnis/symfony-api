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
            var_dump('lade');
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
        if ('query' == array_shift($location) && isset($query_values[$name])){
            $converter->setValue($query_values[$name]);
        } else if ('path' == array_shift($location) && isset($path_values[$name])){
            $converter->setValue($path_values[$name]);
        } else if ('body' == array_shift($location) && isset($path_values[$name])){
            $data = json_decode($request->getContent(), true);
            $converter->setValue($data);
        }
        yield $converter->denormalize();
    }
}