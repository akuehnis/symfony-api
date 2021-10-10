<?php
namespace Akuehnis\SymfonyApi\Converter;

use Doctrine\Common\Annotations\AnnotationReader;

/** @Annotation */
class BaseModelConverter extends ValueConverter
{

    protected $class_name = '\Akuehnis\SymfonyApi\Models\BaseModel';

    protected array $schema = [
        'type' => 'object'
    ];

    public function __construct($params = [])
    {
        parent::__construct($params);
        if (isset($params['class_name'])){
            $this->setClassName($params['class_name']);
        }
    }

    public function setClassName($class_name){
        $this->class_name = $class_name;
    }

    public function getClassName(){
        return $this->class_name;
    }

    public function getAllClassNames():array
    {
        $class_names = [$this->getClassName()];
        foreach ($this->getPropertyConverters() as $converter){
            if (is_subclass_of($converter, 'Akuehnis\SymfonyApi\Converter\BaseModelConverter')){
                $class_names = array_merge($class_names, $converter->getAllClassNames());
            }
        }

        return $class_names;
    }

    public function getClassNameShort(){
        $reflect = new \ReflectionClass($this->getClassName());
        return $reflect->getShortName();
    }

    public function denormalize($data)
    {
        if (null === $data){
            return null;
        }
        $class_name = $this->getClassName();
        $obj = new $class_name();
        foreach ($this->getPropertyConverters() as $converter){
            $name = $converter->getName();
            if (isset($data[$converter->getName()])){
                $value = $converter->denormalize($data[$converter->getName()]);
            } else {
                $value = $converter->denormalize($converter->getDefaultValue());
            }
            $obj->{$name} = $value;
        }

        return $obj;

    }

    public function normalize($obj)
    {
        if (null === $obj){
            return null;
        }
        $class_name = $this->getClassName();
        $arr = [];
        foreach ($this->getPropertyConverters() as $converter){
            $name = $converter->getName();
            if (property_exists($obj, $name)){
                $value = $converter->normalize($obj->{$name});
            } else {
                $converter->normalize($converter->getDefaultValue());
            }
            $arr[$name] = $value;
        }

        return $arr;

    }

    public function getSchema():array 
    {
        $property_schemas = [];
        foreach ($this->getPropertyConverters() as $converter){
            $name = $converter->getName();
            $schema = $converter->getSchema();
            if (get_class($converter) == get_class($this)) {
                $schema = [
                    '$ref' => '#/components/schemas/' . $converter->getClassNameShort(),
                ];
            }
            $property_schemas[$name] = $schema;
        }
        $class_schema = [
            'type' => 'object',
            'properties' => $property_schemas,
        ];

        return $class_schema;

    }

    public function getPropertySchemas():array
    {
        // wird bei Array separeat benutzt
        $properties = [];
        foreach ($this->getPropertyConverters() as $converter){
            $name = $converter->getName();
            $schema = $converter->getSchema();
            if (get_class($converter) == get_class($this)) {
                $schema = [
                    '$ref' => '#/components/schemas/' . $converter->getClassNameShort(),
                ];
            }
            $properties[$name] = $schema;
        }
        return $properties;
    }

    public function validate($data):array
    {
        $errors = [];
        foreach ($this->getPropertyConverters() as $converter){
            $name = $converter->getName();
            if ($converter->getRequired() && !isset($data[$name])) {
                $errors[] = [
                    'loc' => $converter->getLocation(),
                    'msg' => 'Required',
                ];
            } else if (!$converter->getNullable() && isset($data[$name]) && null === $data[$name]){
                $errors[] = [
                    'loc' => $converter->getLocation(),
                    'msg' => 'Null not allowed',
                ];
            } else if (isset($data[$name])) {
                $violations = $converter->validate($data[$name]);
                if (0 < count($violations)){
                    $errors = array_merge($errors, $violations);
                }
            }
        }
        return $errors;
    }

    /** 
     * Returns the converters for the classes public properties
     */
    public function getPropertyConverters()
    {
        $class_name = $this->getClassName();
        $reflection = new \ReflectionClass($class_name);
        $instance = new $class_name();
        $converters = [];
        foreach ($reflection->getProperties() as $reflection_property){
            if (!$reflection_property->isPublic()){
                continue;
            }
            $name = $reflection_property->getName();
            $reflection_named_type = $reflection_property->getType();
            $type = $reflection_named_type->getName();
            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getPropertyAnnotations($reflection_property);
            $converter_annotations = array_filter($annotations, function($item) {
                return is_subclass_of(get_class($item), 'Akuehnis\SymfonyApi\Converter\ValueConverter');
            });
            $converter = null;
            if (0 < count($converter_annotations)){
                $converter = array_shift($converter_annotations);
            }
            if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
                // For base types we have a converter ready to use
                $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                if ($reflection_property->isInitialized($instance)){
                    $converter = new $className([
                        'default_value' => $reflection_property->getValue($instance),
                        'required' => false,
                        'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                        'name' => $name,
                    ]);
                } else {
                    $converter = new $className([
                        'required' => true,
                        'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                        'name' => $name,
                    ]);
                }
            }
            if (!$converter && is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\BaseModel')){
                $className = 'Akuehnis\SymfonyApi\Converter\BaseModelConverter';
                if ($reflection_property->isInitialized($instance)){
                    $converter = new $className([
                        'default_value' => $reflection_property->getValue($instance),
                        'required' => false,
                        'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                        'name' => $name,
                        'class_name' => $type,
                    ]);   
                } else {
                    $converter = new $className([
                        'required' => true,
                        'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                        'name' => $name,
                        'class_name' => $type,
                    ]);
                }
            }

            if ($converter){
                $location = ['body', $name];
                $converter->setLocation($location);
                $converters[] = $converter;
            }

        }

        return $converters;

    }

    
}