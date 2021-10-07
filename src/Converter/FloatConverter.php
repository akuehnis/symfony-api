<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class FloatConverter extends ValueConverter
{

    /**
     * @Assert\Type("float")
     */
    public $value;

    private array $schema = [
        'type' => 'number',
        'format' => 'float'
    ];

    public function denormalize(){
        return (float)$this->value;
    }

    public function normalize($value){
        $this->value = (float) $value;
    }

    public function validate():array
    {
        $errors = [];
        if (!is_float($this->value)){
            $errors[] = 'Value must be float';
        }
        return $errors;
    }
    
}