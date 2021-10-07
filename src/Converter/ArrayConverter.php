<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class ArrayConverter extends ValueConverter
{

    /**
     * @Assert\Type("array")
     */
    public $value;

    private array $schema = [
        'type' => 'array'
    ];

    public function denormalize(){
        return (array)$this->value;
    }

    public function normalize($value){
        $this->value = (array) $value;
    }

    public function validate():array
    {
        $errors = [];
        if (!is_array($this->value)){
            $errors[] = 'Value must be array';
        }
        return $errors;
    }
}