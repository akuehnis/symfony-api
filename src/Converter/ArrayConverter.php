<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class ArrayConverter extends ValueConverter
{

    private array $schema = [
        'type' => 'array'
    ];

    public function denormalize($value){
        return (array)$value;
    }

    public function normalize($value){
        return (array) $value;
    }

    public function validate($value):array
    {
        $errors = [];
        if (!is_array($value)){
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must be of type array',
            ];
        }
        return $errors;
    }
}