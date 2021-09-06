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
    public string $title = '';

    /**
     * Description of the value in Openapi
     */
    public $description = '';

    public string $type = '';

    public ?string $format = null;

    /**
     * Property Name. Only for property converters which must be passed as method annotation (prior to PHP 8.1)
     */
    public ?string $property_name = null;

    public function __construct($params = [])
    {
        if (isset($params['defaultValue'])){
            $this->normalize($params['defaultValue']);
        }
        if (isset($params['title'])){
            $this->title = $params['title'];
        }
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
    public function getTitle(){
        return $this->title;
    }

    public function getType(){
        return $this->type;
    }
    public function getFormat(){
        return $this->format;
    }
}