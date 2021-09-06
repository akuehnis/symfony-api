<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class FloatConverter extends ApiConverter
{

    /**
     * @Assert\Type("float")
     */
    public $value;

    public string $type = 'number';

    public ?string $format = 'float';

    public function denormalize(){
        return (float)$this->value;
    }

    public function normalize($value){
        $this->value = (float) $value;
    }
}