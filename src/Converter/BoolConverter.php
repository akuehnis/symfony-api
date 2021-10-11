<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class BoolConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'boolean'
    ];

    protected array $true_values = [
        true,
        'true',
        'TRUE',
        1,
        '1',
    ];

    protected array $false_values = [
        false,
        'false',
        'FALSE',
        0,
        '0',
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
                if (in_array($val, $this->true_values, true)){
                    return true;
                } else {
                    return false;
                }
            }, $value);
        } else {
            if (in_array($value, $this->true_values, true)){
                return true;
            } else {
                return false;
            }
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
                if (in_array($val, $this->true_values, true)){
                    return true;
                } else {
                    return false;
                }
            }, $value);
        } else {
            if (in_array($value, $this->true_values, true)){
                return true;
            } else {
                return false;
            }
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
                    if (!in_array($val, $this->true_values, true) && !in_array($val, $this->false_values, true)){
                        $errors[] = [
                            'loc' => array_merge($this->getLocation(), [$i]),
                            'msg' => 'Value must be of type bool',
                        ];
                    }
                }
            }
        } else {
            if (!in_array($value, $this->true_values, true) && !in_array($value, $this->false_values, true)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be float',
                ];
            }
        }

        return $errors;
    }
}