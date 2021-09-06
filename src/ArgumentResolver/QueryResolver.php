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

    private $base_types =  ['string', 'int', 'float', 'bool', 'array'];

    public function __construct(RouteService $RouteService)
    {
        $this->RouteService = $RouteService;
    }

    /**
     * check if the parameter type is supported
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if (!$this->RouteService->isApiRoute($request)){
            return false;
        }
        // converter can be defined as method annotation
        $converter = $this->getConverterFromAnnotation($request, $argument);
        if ($converter) {
            return true;
        }
        
        // base types automatically have a converter
        $type = $argument->getType();
        if (in_array($type, $this->base_types)) {
            return true;
        }

        // converters can be passed as default values (from PHP 8.1)
        if (version_compare(PHP_VERSION, '8.1.', '>=')){
            if (!$argument->hasDefaultValue()){
                return false;
            }
            $defaultValue = $argument->getDefaultValue();
            if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves the controller method parameters
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        $name = $argument->getName();
        $converter = $this->getConverterFromAnnotation($request, $argument);
        $defaultValue = $argument->getDefaultValue();
        if (version_compare(PHP_VERSION, '8.1.', '>=')){
            if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                // even if converter is found in annotations, converters passed as default value must have priority
                $converter = $defaultValue;
            }
        }
        if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
            $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            $converter = new $className(['defaultValue' => $defaultValue]); 
        }
        if (!$converter){
            return;
        }
        $value = $request->query->get($name);
        if (null !== $value){
            $converter->value = $value;
        } 
        yield $converter->denormalize();
    }

    


    public function getConverterFromAnnotation($request, $argument){
        $name = $argument->getName();
        $annotationReader = new AnnotationReader();
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return null;
        }
        $route = $this->RouteService->getRouteByName($routeName);
        $reflection = $this->RouteService->getMethodReflection($route);
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        $converter_annotations = array_filter($annotations, function($item) use ($name) {
            return is_subclass_of($item, 'Akuehnis\SymfonyApi\Converter\ApiConverter')
                && $item->property_name == $name;
        });
        $converter = array_shift($converter_annotations);

        return $converter;
    }
}