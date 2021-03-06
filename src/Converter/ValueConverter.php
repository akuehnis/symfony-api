<?php
namespace Akuehnis\SymfonyApi\Converter;

/** @Annotation */
class ValueConverter
{

    /**
     * The default value, do not specify a type
     * 
     */
    protected $default_value;

    /**
     * Property Name.
     */
    protected string $name = '';

    /**
     * Description of the value in Openapi
     */
    protected string $description = '';

    /**
     * openapi schema. 'nullable' is part of the schema
     */
    protected array $schema = [];

    /**
     * definition if parameter is required. If not, there must be a default value
     */
    protected bool $required = false;

    /**
     * definition if parameter is nullable.
     */
    protected bool $nullable = false;

    /**
     * definition if parameter is an array.
     */
    protected $is_array = false;

    /**
     * location.
     */
    protected array $location = [];


    public function __construct($params = [])
    {
        if (isset($params['default_value'])){
            $this->setDefaultValue($this->normalize($params['default_value']));
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
            if (true===$params['required']
                || 'true' === $params['required']
                || 'TRUE' === $params['required']
                || 1 === $params['required']
                || '1' === $params['required']
            ) {
                $this->setRequired(true);
            } else {
                $this->setRequired(false);
            }
        }
        if (isset($params['nullable'])){
            if (true===$params['nullable']
                || 'true' === $params['nullable']
                || 'TRUE' === $params['nullable']
                || 1 === $params['nullable']
                || '1' === $params['nullable']
            ) {
                $this->setNullable(true);
            } else {
                $this->setNullable(false);
            }
        }
        if (isset($params['is_array'])){
            if (true===$params['is_array']
                || 'true' === $params['is_array']
                || 'TRUE' === $params['is_array']
                || 1 === $params['is_array']
                || '1' === $params['is_array']
            ) {
                $this->setIsArray(true);
            } else {
                $this->setIsArray(false);
            }
        }
        if (isset($params['location'])){
            $this->setLocation($params['location']);
        }
    }

    /**
     * Converts api's value to internal value
     */
    public function denormalize($value){
        return $value;
    }

    /**
     * Converts the internal value to api's value
     */
    public function normalize($value){
        return $value;
    }

    /**
     * Set the default value, do not specify type
     */
    public function setDefaultValue($default_value){
        $this->default_value = $default_value;
    }

    /**
     * Get the default value, do not specify type
     */
    public function getDefaultValue(){
        return $this->default_value;
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
        if ($this->getIsArray()) {
            $schema =  [
                'type' => 'array',
                'items' => $this->schema
            ];
        } else {
            $schema = $this->schema;
        }
        if ($this->getNullable()){
            $schema['nullable'] = true;
        }
        if (!$this->getRequired()){
            $schema['default'] = $this->getDefaultValue();
        }

        return $schema;
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }


    public function getName():string
    {
        return $this->name;
    }

    public function setRequired(bool $required)
    {
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

    public function setIsArray(bool $is_array){
        $this->is_array = $is_array;
    }


    public function getIsArray():bool
    {
        return $this->is_array;
    }

    public function setLocation(array $location){
        $this->location = $location;
    }

    public function getLocation():array
    {
        return $this->location;
    }

    /**
     * Returns list of errors empty array if valid
     * 
     * an error is an array of this format:
     * [
     *      loc: []string
     *      msg: string
     * ]
     */
    public function validate($value):array
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