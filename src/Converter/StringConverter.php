<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class StringConverter extends ValueConverter
{

    private array $schema = [
        'type' => 'string'
    ];

    public function denormalize($value){
        return (string)$value;
    }

    public function normalize($value){
        return (string) $value;
    }

    public function validate($value):array
    {
        $errors = [];
        if (!is_string($value)){
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must be string',
            ];
        }
        return $errors;
    }
}