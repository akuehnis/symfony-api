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
        return array_map(function($item){
            return (bool) $item;
        }, $data);

    }

    public function normalize($data)
    {
        return array_map(function($item){
            return (bool) $item;
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
            foreach ($data as $i => $val){
                if (!is_bool($val)){
                    $errors[] = [
                        'loc' => array_merge($this->getLocation(), [$i]),
                        'msg' => 'Value must be of type bool',
                    ];
                }
            }
        }
        return $errors;
    }
}