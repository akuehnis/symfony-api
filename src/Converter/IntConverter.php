<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class IntConverter extends ValueConverter
{

    /**
     */
    public $value;


    private array $schema = [
        'type' => 'integer',
    ];

    public function denormalize(){
        return (int)$this->value;
    }

    public function normalize( $value){
        $this->value = (int) $value;
    }

    public function validate():array
    {
        $errors = [];
        if (false === preg_match('/^[0-9]+/', $this->value)) {
            $errors[] = 'Only numbers allowed';
        }
        return $errors;
    }
    
}