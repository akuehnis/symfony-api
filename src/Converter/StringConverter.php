<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class StringConverter extends ValueConverter
{

    /**
     * @Assert\Type("string")
     */
    public $value;

    private array $schema = [
        'type' => 'string'
    ];

    public function denormalize(){
        return (string)$this->value;
    }

    public function normalize($value){
        $this->value = (string) $value;
    }

    public function validate():array
    {
        $errors = [];
        if (!is_string($this->value)){
            $errors[] = 'Value must be string';
        }
        return $errors;
    }
}