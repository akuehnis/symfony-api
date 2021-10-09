<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class BoolConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'boolean'
    ];

    public function denormalize($value){
        return (bool)$value;
    }

    public function normalize($value){
        return (bool) $value;
    }

    public function validate($value):array
    {
        return [];
    }
}