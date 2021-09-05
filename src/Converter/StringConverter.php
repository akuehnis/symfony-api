<?php
namespace Akuehnis\SymfonyApi\Converter;


class StringConverter extends ApiConverter
{

    public $value;

    public function denormalize(){
        return (string)$this->value;
    }

    public function normalize($value){
        $this->value = (string) $value;
    }
}