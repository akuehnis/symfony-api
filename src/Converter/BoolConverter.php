<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class BoolConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'boolean'
    ];

    public function denormalize($value){
        if (null === $value){
            return null;
        } else if (
            $value === true
            || $value === 'true'
            || $value === 'TRUE'
            || $value === 1
            || $value === '1'
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function normalize($value){
        if (null === $value){
            return null;
        } else if (
            $value === true
            || $value === 'true'
            || $value === 'TRUE'
            || $value === 1
            || $value === '1'
        ) {
            return true;
        } else {
            return false;
        }
    }

    public function validate($value):array
    {
        $errors = [];
        if (
            $value === true 
            || $value === 'true'
            || $value === 'TRUE'
            || $value === 1
            || $value === '1'
            || $value === false
            || $value === 'false'
            || $value === 'FALSE'
            || $value === 0
            || $value === '0'
            || $value === null
        ){
            // all these values are ok
        } else {
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must be type bool',
            ];
        }
    
        return $errors;
    }
}