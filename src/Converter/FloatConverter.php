<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class FloatConverter extends ValueConverter
{

    private array $schema = [
        'type' => 'number',
        'format' => 'float'
    ];

    public function denormalize($value){
        return (float)$value;
    }

    public function normalize($value){
        return (float) $value;
    }

    public function validate($value):array
    {
        $errors = [];
        if (!is_float($value)){
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must be of type float',
            ];
        }
        return $errors;
    }
    
}