<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;


class IntConverter extends ApiConverter
{

    /**
     * 
     * @Assert\Regex(
     * pattern="/^[0-9]+/",
     * message="Only numbers allowed"
     * )
     */
    public $value;

    public function denormalize(){
        return (int)$this->value;
    }

    public function normalize( $value){
        $this->value = (int) $value;
    }
}