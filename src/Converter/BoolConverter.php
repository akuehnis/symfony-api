<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class BoolConverter extends ApiConverter
{

    public $value;

    public function denormalize(){
        return (bool)$this->value;
    }

    public function normalize($value){
        $this->value = (bool) $value;
    }
}