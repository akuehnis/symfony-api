<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class ValueConverter
{

    /**
     * The Api Value
     * 
     * However, there are getters and setters for this value.
     * 
     */
    public $value;

    /**
     * Property Name.
     */
    private string $name = '';

    /**
     * Description of the value in Openapi
     */
    private $description = '';

    /**
     * openapi schema. 'nullable' is part of the schema
     */
    private array $schema = [];

    /**
     * definition if parameter is required. If not, there must be a default value
     */
    private $required = false;

    /**
     * definition if parameter is nullable.
     */
    private $nullable = false;

    /**
     * location.
     */
    private array $location = [];


    public function __construct($params = [])
    {
        if (isset($params['default_value'])){
            $this->normalize($params['default_value']);
        }
        if (isset($params['schema'])){
            $this->setSchema($params['schema']);
        }
        if (isset($params['description'])){
            $this->setDescription($params['description']);
        }
        if (isset($params['name'])){
            $this->setName($params['name']);
        }
        if (isset($params['required'])){
            $this->setRequired($params['required']);
        }
        if (isset($params['nullable'])){
            $this->setNullable($params['nullable']);
        }
        if (isset($params['location'])){
            $this->setLocation($params['location']);
        }
    }

    /**
     * Turns the API Value into the internal Value
     */
    public function denormalize(){
        return $this->value;
    }

    /**
     * Turns the Internal value into the API value
     */
    public function normalize($value){
        $this->value = $value;
    }

    /**
     * Set the API-Side Value
     */
    public function setValue($value){
        $this->value = $value;
    }

    /**
     * Get the API-Side Value
     */
    public function getValue(){
        return $this->value;
    }

    /**
     * Set the value OpenAPI description 
     */
    public function setDescription(string $description)
    {
        $this->description = $description;
    }

    /**
     * Returns the parameter description 
     */
    public function getDescription():string
    {
        return $this->description;
    }

    /**
     * Set the openapi property schema as array
     */
    public function setSchema(array $schema)
    {
        $this->schema = $schema;
    }

    public function getSchema():array
    {
        return $this->schema;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }


    public function getName():string
    {
        return $this->name;
    }

    public function setRequired(bool $required){
        $this->required = $required;
    }


    public function getRequired():bool
    {
        return $this->required;
    }

    public function setNullable(bool $nullable){
        $this->nullable = $nullable;
    }


    public function getNullable():bool
    {
        return $this->nullable;
    }

    public function setLocation(array $location){
        $this->location = $location;
    }

    public function getLocation():array
    {
        return $this->location;
    }

    /**
     * Returns list of error strings or empty array if valid
     */
    public function validate():array
    {
        return [];
    }

    public function __toString(){
        return json_encode([
            'name' => $this->getName(),
            'required' => $this->getRequired(),
            'schema' => $this->getSchema(),
            'nullable' => $this->getNullable(),
        ]);
    }

}