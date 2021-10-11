<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class FloatConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'number',
        'format' => 'float'
    ];

    public function denormalize($value){
        if (null === $value){
            return null;
        }
        if (is_array($value)) {
            return array_map(function($val){
                if (null === $val){
                    return null;
                }
                return (float) $val;
            }, $value);
        } else {
            return (float)$value;
        }
    }

    public function normalize($value){
        if (null === $value){
            return null;
        }
        if (is_array($value)) {
            return array_map(function($val){
                if (null === $val){
                    return null;
                }
                return (float) $val;
            }, $value);
        } else {
            return (float)$value;
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
                    if (!is_float($val)){
                        $errors[] = [
                            'loc' => array_merge($this->getLocation(), [$i]),
                            'msg' => 'Value must be of type flost',
                        ];
                    }
                }
            }
        } else {
            if (!is_float($value)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be float',
                ];
            }
        }
        return $errors;

    }
    
}