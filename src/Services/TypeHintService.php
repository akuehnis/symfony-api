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

        return $param;
    }

    public function getParameterModels($route) 
    {
        $reflection = $this->RouteService->getMethodReflection($route);
        if (null === $reflection){
            return [];
        }
        $parameters = $reflection->getParameters();
        $list = [];
        foreach ($parameters as $parameter){
            $name = $parameter->getName();
            $list[$name] = new ParaModel();
            $list[$name]->location = 'query';
            $list[$name]->name = $name;
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $list[$name]->location = 'path';
            } else if (is_subclass_of($parameter->getType()->getName(), 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $list[$name]->location = 'body';
            }
            $list[$name]->type = $parameter->getType()->getName();
            $list[$name]->required = !$parameter->isDefaultValueAvailable();
            $list[$name]->has_default = $parameter->isDefaultValueAvailable();
            $list[$name]->is_nullable = $parameter->isDefaultValueAvailable() && null === $parameter->getDefaultValue();
            if ($parameter->isDefaultValueAvailable()){
                $list[$name]->default = $parameter->getDefaultValue();
            }
        }

        return $list;
    }

    public function getClasses($route){
        $parameters = $this->getParameterModels($route);
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