<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class StringConverter extends ApiConverter
{

    /**
     * @Assert\Type("string")
     */
    public $value;

    public function denormalize(){
        return (string)$this->value;
    }

    public function normalize($value){
        $this->value = (string) $value;
    }
}