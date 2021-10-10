<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation 
 * 
 * This is the default array converter, Same as StringArrayConverter
*/

class ArrayConverter extends ValueConverter
{

    protected array $schema = [
        'type' => 'array',
        'items' => [
            'type' => 'boolean'
        ]
    ];

    public function denormalize($data)
    {
        return array_map(function($value){
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
        }, $data);

    }

    public function normalize($data)
    {
        return array_map(function($item){
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
        }, $data);
    }

    public function validate($data):array
    {
        $errors = [];
        if (!is_array($data)){
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must be of type array',
            ];
        } else {
            foreach ($data as $i => $value){
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
                        'loc' => array_merge($this->getLocation(), [$i]),
                        'msg' => 'Value must be type bool',
                    ];
                }
            }
        }
        return $errors;
    }
}