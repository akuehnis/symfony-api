<?php
namespace Akuehnis\SymfonyApi\Services;

use Akuehnis\SymfonyApi\Models\ParaModel;

class DocBlockService {

    public function getDocblock(string $docComment) 
    {
        $factory  = \phpDocumentor\Reflection\DocBlockFactory::createInstance();
        return $factory->create($docComment);
    }

    public function getMethodSummary(string $docComment)
    {
        $docblock = $this->getDocblock($docComment);
        return $docblock->getSummary();
    }

    public function getMethodDescription(string $docComment) 
    {
        $docblock = $this->getDocblock($docComment);
        return $docblock->getDescription()->getBodyTemplate();
    }

    public function getMethodReturnTag(string $docComment)
    {
        $docblock = $this->getDocblock($docComment);
        foreach($docblock->getTags() as $tag){
            if ('return' == $tag->getName()){
                return $tag;
            }
        }

        return null;

    }

    public function getMethodReturnModel(string $docComment)
    {
        $tag = $this->getMethodReturnTag($docComment);
        if (null === $tag){
            return null;
        }
        $res = new ParaModel();
        $res->description = $tag->getDescription()->getBodyTemplate();
        $tagType = $tag->getType();
        if ('phpDocumentor\Reflection\Types\Array_' == get_class($tagType)){
            $res->type = 'array';
            $res->items = new ParaModel();
            $valueType = $tagType->getValueType();
            if ('phpDocumentor\Reflection\Types\Object_' == get_class($valueType)){
                $res->items->type = $valueType->getValueType()->getFqsen()->getName();
            } else if ($valueType){
                $res->items->type = $valueType->__toString();
            }
        } else {
            $res->type = $tagType->__toString();
        }

        return $res;

    }

    public function getParameters(string $docComment) 
    {
        $docblock = $this->getDocblock($docComment);
        $params = [];
        foreach($docblock->getTags() as $tag){
            if ('param' == $tag->getName()){
                $name = $tag->getVariableName();
                if ($name){
                    $params[$name] = new ParaModel();
                    $params[$name]->description = $tag->getDescription()->getBodyTemplate();
                    $tagType = $tag->getType();
                    if ('phpDocumentor\Reflection\Types\Array_' == get_class($tagType)){
                        $params[$name]->type = 'array';
                        $params[$name]->items = new ParaModel();
                        $valueType = $tagType->getValueType();
                        if ('phpDocumentor\Reflection\Types\Object_' == get_class($valueType)){
                            $params[$name]->items->type = $valueType->getValueType()->getFqsen()->getName();
                        } else {
                            $params[$name]->items->type = $valueType->__toString();
                        }
                    } else {
                        $params[$name]->type = $tagType->__toString();
                    }
                }
            }
        }
        
        return $params;
    }

    public function getClasses($docComment){
        $parameters = $this->getParameters($docComment);
        $returnModel = $this->getMethodReturnModel($docComment);
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