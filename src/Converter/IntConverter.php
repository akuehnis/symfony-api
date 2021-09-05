<?php
namespace Akuehnis\SymfonyApi\Converter;



class IntConverter extends ApiConverter
{

    public $value;

    public function denormalize(){
        return (int)$this->value;
    }

    public function normalize( $value){
        $this->value = (int) $value;
    }
}