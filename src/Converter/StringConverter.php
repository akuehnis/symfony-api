<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class StringConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'string'
    ];

    public function denormalize($value)
    {
        if (null === $value){
            return null;
        }
        if (is_array($value)){
            return array_map(function($val){
                if (null === $val){
                    return null;
                }
                return (string) $val;
            }, $value);
        } else {
            return (string)$value;
        }
    }

    public function normalize($value)
    {
        if (null === $value){
            return null;
        }
        if (is_array($value)){
            return array_map(function($val){
                if (null === $val){
                    return null;
                }
                return (string) $val;
            }, $value);
        } else {
            return (string)$value;
        }
    }

    public function validate($value):array
    {
        $errors = [];
        if (!$this->getNullable() && null === $value) {
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must not be null',
            ];
        } else if (is_array($value)) {
            if (!is_array($value)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be of type array',
                ];
            } else {
                foreach ($value as $i => $val){
                    if (!is_string($val)){
                        $errors[] = [
                            'loc' => array_merge($this->getLocation(), [$i]),
                            'msg' => 'Value must be of type string',
                        ];
                    }
                }
            }
        } else {
            if (!is_string($value)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be string',
                ];
            }
        }
        return $errors;
    }
}