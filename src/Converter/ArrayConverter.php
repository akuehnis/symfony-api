<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class ArrayConverter extends ApiConverter
{

    /**
     * @Assert\Type("array")
     */
    public $value;

    public function denormalize(){
        return (array)$this->value;
    }

    public function normalize($value){
        $this->value = (array) $value;
    }
}