<?php
namespace Akuehnis\SymfonyApi\Services;

use Doctrine\Common\Annotations\AnnotationReader;
use Akuehnis\SymfonyApi\Models\ParaModel;
use Akuehnis\SymfonyApi\Services\RouteService;

class TypeHintService {

    protected $RouteService;

    public function __construct(RouteService $RouteService)
    {
        $this->RouteService = $RouteService;
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
    
    public function getMethodReturnModel($route)
    {
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return null;
        }
        $returnType = $reflection->getReturnType();

        $param = new ParaModel();
        $param->type = null === $returnType ? 'string' : $reflection->getReturnType()->getName();
        if ('array' == $param->type){
            $param->items = new ParaModel();
        }
        return $param;
    }

    /**
     * Return Paramodels for route parameters 
     * 
     * @param Route $route Symfony Route 
     * @return ParaModel[] array of Paramodels
     */
    public function getRouteParameterModels($route) 
    {
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return [];
        }
        $parameters = $reflection->getParameters();
        $list = [];
        foreach ($parameters as $parameter){
            $type = $parameter->getType()->getName();
            if (!in_array($type, ['bool', 'int', 'string', 'float', 'array'])){
                continue;
            }
            $name = $parameter->getName();
            $list[$name] = new ParaModel();
            $list[$name]->location = 'query';
            $list[$name]->name = $name;
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $list[$name]->location = 'path';
            } else if (is_subclass_of($parameter->getType()->getName(), 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $list[$name]->location = 'body';
            }
            $list[$name]->type = $type;
            if ('array' ==  $list[$name]->type){
                $list[$name]->items = new ParaModel();
            }
            $list[$name]->required = !$parameter->isDefaultValueAvailable();
            $list[$name]->has_default = $parameter->isDefaultValueAvailable();
            $list[$name]->is_nullable = $parameter->isDefaultValueAvailable() && null === $parameter->getDefaultValue();
            if ($parameter->isDefaultValueAvailable()){
                $list[$name]->default = $parameter->getDefaultValue();
            }

        }

        return $list;
    }

    /**
     * Return Paramodel for class properties
     * 
     * @param string $classname 
     * @return ParaModel[] array of Paramodels
     */
    public function getClassPropertyModels($classname)
    {
        $class_vars = get_class_vars($classname);
        $reflection = new \ReflectionClass($classname);
        $list = [];
        foreach ($reflection->getProperties() as $property){
            //ReflectionProperty::hasDefaultValue() erst ab PHP 8
            $name = $property->getName();
            $list[$name] = new ParaModel();
            $list[$name]->location = $classname;
            $list[$name]->name = $name;
            $list[$name]->type = $property->hasType() ? $property->getType()->getName() : null;
            if ('array' ==  $list[$name]->type){
                $list[$name]->items = new ParaModel();
            }
            $list[$name]->required = isset($class_vars[$name]);
            $list[$name]->has_default = isset($class_vars[$name]);
            $list[$name]->is_nullable = true; // todo - maybe with PHP 8 only?
            if (isset($class_vars[$name])){
                $list[$name]->default = $class_vars[$name];
            }
        }

        return $list;
    }

    public function getClasses($route){
        $parameters = $this->getRouteParameterModels($route);
        $returnModel = $this->getMethodReturnModel($route);
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
}