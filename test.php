<?php 
namespace Mynamespace;
ini_set('display_errors',1);
//error_reporting(E_ALL);

class Specialtype {}

class Giga
{
    /**
     * Hier ist der Docblock
     */
    public string $required_not_null;
    public ?string $required_nullable;
    public string $not_required_not_null = "adf";
    public ?string $not_required_nullable = null;
    public array $array_type = [];
    public ?Specialtype $special_type = null;


    public $property_not_typehinted;

    /**
     * Method docBlock
     */
    public function checkParameters(
        string $required_not_null,
        ?string $required_nullable,
        string $not_required_not_null = "test",
        ?string $not_required_nullable = null,
        ?Specialtype $special_type=null,
        $parameter_without_typehint = null
    ){
        // nothing to do
    }
}

// Properties
$c = new \Mynamespace\Giga();
$reflection = new \ReflectionClass('Mynamespace\Giga');

echo 'Class Properties'. "\n";
foreach ($reflection->getProperties() as $property){
    $ref_named_type = $property->getType();
    var_dump([
        'name'              => $property->getName(),
        'has_type'          => $property->hasType(),
        'type'              => $ref_named_type ? $ref_named_type->getName() : null,
        'required'          => !$property->isInitialized($c),
        'has_default_value' => $property->isInitialized($c),
        'default_value'     => $property->isInitialized($c) ? $property->getValue($c) : false,
        'nullable'          => $ref_named_type ? $ref_named_type->allowsNull() : false,
        'docComment'        => $property->getDocComment(),
    ]);
}

echo 'Method Parameters'. "\n";
$ref_method = new \ReflectionMethod('Mynamespace\Giga', 'checkParameters');
$ref_method = $reflection->getMethod('checkParameters');

foreach ($ref_method->getParameters() as $parameter){
    $ref_named_type = $parameter->getType();
    var_dump([
        'name'              => $parameter->getName(),
        'has_type'          => $parameter->hasType(),
        'type'              => $ref_named_type ? $ref_named_type->getName() : null,
        'required'          => !$parameter->isOptional(),
        'has_default_value' => $parameter->isDefaultValueAvailable(),
        'default_value'     => $parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : false,
        'docComment'        => $ref_method->getDocComment(),
        'nullable'          => $ref_named_type ? $ref_named_type->allowsNull() : false,
    ]);

}