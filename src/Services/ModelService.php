<?php

namespace Akuehnis\SymfonyApi\Services;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Doctrine\Common\Annotations\AnnotationReader;

class ModelService
{


    public function getPropertyConverter($reflection_property){
        $reflection_type = $reflection_property->getType();
        if (!$reflection_type) {
            return null;
        }
        $type = $reflection_type->getName();
        if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            return null;
        }
        $annotations = $this->getPropertyAnnotations($reflection_property, 'Akuehnis\SymfonyApi\Converter\ApiConverter');
        $converter = array_shift($annotations);
        if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
            $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            if ($parameter->isDefaultValueAvailable()){
                $converter = new $className(['default_value' => $parameter->getDefaultValue()]);
            } else {
                $converter = new $className([]);
            }
        }
        
        return $converter;

    }

    public function getPropertyAnnotations($property, $filter_class)
    {
        $annotationReader = new AnnotationReader();
        $annotations = $annotationReader->getPropertyAnnotations($property);
        if ($filter_class) {
            $annotations = array_filter($annotations, function($item) use ($filter_class){
                return is_subclass_of($item, $filter_class);
            });
        }

        return $annotations;
    }
    
}