<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class BoolConverter extends ValueConverter
{

    public $value;

    private array $schema = [
        'type' => 'boolean'
    ];

    public function denormalize(){
        return (bool)$this->value;
    }

    public function normalize($value){
        $this->value = (bool) $value;
    }

    public function validate():array
    {
        return [];
    }
}