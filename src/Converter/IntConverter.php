<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class IntConverter extends ValueConverter
{


    private array $schema = [
        'type' => 'integer',
    ];

    public function denormalize($value){
        return (int)$value;
    }

    public function normalize( $value){
        return (int) $value;
    }

    public function validate($value):array
    {
        $errors = [];
        if (!preg_match('/^[0-9]+/', $value)) {
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Only integers allowed',
            ];
        }
        return $errors;
    }
    
}