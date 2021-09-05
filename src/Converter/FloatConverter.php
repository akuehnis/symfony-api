<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

class FloatConverter extends ApiConverter
{

    /**
     * @Assert\Type("float")
     */
    public $value;

    public function denormalize(){
        return (float)$this->value;
    }

    public function normalize($value){
        $this->value = (float) $value;
    }
}