<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class ApiConverter
{

    /**
     * The Api Value
     * And Annotations for it's validation
     */
    public $value;

    /**
     * Title of the value in Openapi
     */
    public $title = '';

    /**
     * Description of the value in Openapi
     */
    public $description = '';

    /**
     * Property Name. Only for property converters which must be passed as method annotation (prior to PHP 8.1)
     */
    public ?string $property_name = null;

    public function __construct($params = [])
    {
        if (isset($params['defaultValue'])){
            $this->normalize($params['defaultValue']);
        }
        $this->title = isset($params['title']) ? (string) $params['title'] : get_class($this);
        $this->description = isset($params['description']) ? (string) $params['description'] : '';
        $this->property_name = isset($params['property_name']) ? (string) $params['property_name'] : null;
    }

    /**
     * Turns the Api-Value into the internal Value
     */
    public function denormalize(){
        return $this->value;
    }

    /**
     * Turns the Internal value into the Api
     */
    public function normalize($value){
        $this->value = $value;
    }

    public function __toString(){
        return get_class($this);
    }
}