<?php
namespace Akuehnis\SymfonyApi\Converter;


class FloatConverter extends ApiConverter
{

    public $value;

    public function denormalize(){
        return (float)$this->value;
    }

    public function normalize($value){
        $this->value = (float) $value;
    }
}