<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class IntConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'integer',
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
                return (int) $val;
            }, $value);
        } else {
            return (int)$value;
        }
    }

    public function normalize( $value){
        if (null === $value){
            return null;
        }
        if (is_array($value)) {
            return array_map(function($val){
                if (null === $val){
                    return null;
                }
                return (int) $val;
            }, $value);
        } else {
            return (int)$value;
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
                    if (!preg_match('/^[0-9]+/', $val)){
                        $errors[] = [
                            'loc' => array_merge($this->getLocation(), [$i]),
                            'msg' => 'Value must be of type integer',
                        ];
                    }
                }
            }
        } else {
            if (!preg_match('/^[0-9]+/', $value)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be of type integer',
                ];
            }
        }

        return $errors;
    }
    
}