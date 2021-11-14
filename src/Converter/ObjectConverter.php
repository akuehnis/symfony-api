<?php
namespace Akuehnis\SymfonyApi\Converter;

use Doctrine\Common\Annotations\AnnotationReader;

/** @Annotation */
class ObjectConverter extends ValueConverter
{

    protected $class_name = '';

    protected array $schema = []; // see getSchema()

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
            if (get_class($converter) ==  'Akuehnis\SymfonyApi\Converter\ObjectConverter' ||
                is_subclass_of($converter, 'Akuehnis\SymfonyApi\Converter\ObjectConverter')
            ){
                $class_names = array_merge($class_names, $converter->getAllClassNames());
            }
        }

        return $class_names;
    }

    public function getClassNameShort()
    {
        $reflect = new \ReflectionClass($this->getClassName());
        return $reflect->getShortName();
    }

    public function denormalize($value)
    {
        if (null === $value){
            return null;
        }
        if ($this->getIsArray()){
            return array_map(function($val){
                $converter = new ObjectConverter(['class_name' => $this->getClassName()]);
                return $converter->denormalize($val);
            }, $value);
        } else {
            $class_name = $this->getClassName();
            $obj = new $class_name();
            foreach ($this->getPropertyConverters() as $converter){
                $name = $converter->getName();
                if (array_key_exists($name, $value)){
                    $val = $converter->denormalize($value[$name]);
                } else {
                    $val = $converter->denormalize($converter->getDefaultValue());
                }
                $obj->{$name} = $val;
            }

            return $obj;
        }

    }

    public function normalize($value)
    {
        if (null === $value){
            return null;
        }
        if (is_array($value) && !$this->getIsArray()){
            throw new \Exception(sprintf('Converter for class %s error: is_array is false.', $this->getClassName()));
        }
        if ($this->getIsArray()){
            return array_map(function($val){
                $converter = new ObjectConverter(['class_name' => $this->getClassName()]);
                return $converter->normalize($val);
            }, $value);
        } else {
            $class_name = $this->getClassName();
            $arr = [];
            foreach ($this->getPropertyConverters() as $converter){
                $name = $converter->getName();
                if (property_exists($value, $name)){
                    $val = $converter->normalize($value->{$name});
                } else {
                    $val = $converter->normalize($converter->getDefaultValue());
                }
                $arr[$name] = $val;
            }
            
            return $arr;
        }

    }

    public function getSchema():array 
    {
        
        if ($this->getIsArray()){
            $converter = new ObjectConverter(['class_name' => $this->getClassName()]);
            $class_schema = [
                'type' => 'array',
                'items' => [
                    '$ref' => '#/components/schemas/' . $converter->getClassNameShort()
                ]
            ];
        } else {
            $property_schemas = [];
            foreach ($this->getPropertyConverters() as $converter){
                $name = $converter->getName();
                $schema = $converter->getSchema();
                if (get_class($converter) == get_class($this) && !$converter->getIsArray()) {
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
        }

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

    public function validate($value):array
    {
        $errors = [];
        if (!$this->getNullable() && null === $value) {
            $errors[] = [
                'loc' => $this->getLocation(),
                'msg' => 'Value must not be null',
            ];
        } else if ($this->getIsArray()){
            if (!is_array($value)){
                $errors[] = [
                    'loc' => $this->getLocation(),
                    'msg' => 'Value must be of type array',
                ];
            } else {
                foreach ($value as $i => $val){
                    $location = $this->getLocation();
                    $location[] = $i;
                    $converter = new ObjectConverter([
                        'class_name' => $this->getClassName(),
                        'location' => $location,
                    ]);
                    $violations = $converter->validate($val);
                    if (0 < count($violations)){
                        $errors = array_merge($errors, $violations);
                    }
                }
            }
        } else {
            foreach ($this->getPropertyConverters() as $converter){
                $name = $converter->getName();
                if (!is_array($value)){
                    $errors[] = [
                        'loc' => $converter->getLocation(),
                        'msg' => 'Required',
                    ];
                }
                else if ($converter->getRequired() && !array_key_exists($name, $value)) {
                    $errors[] = [
                        'loc' => $converter->getLocation(),
                        'msg' => 'Required',
                    ];
                } else if (!$converter->getNullable() && array_key_exists($name, $value) && null === $value[$name]){
                    $errors[] = [
                        'loc' => $converter->getLocation(),
                        'msg' => 'Null not allowed',
                    ];
                } else if (isset($value[$name])) {
                    $violations = $converter->validate($value[$name]);
                    if (0 < count($violations)){
                        $errors = array_merge($errors, $violations);
                    }
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
            if (!$converter && !in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
                $className = 'Akuehnis\SymfonyApi\Converter\ObjectConverter';
                $args = [
                    'required' => true,
                    'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                    'name' => $name,
                    'class_name' => $type,
                ];
                if ($reflection_property->isInitialized($instance)){
                    $args['default_value'] = $reflection_property->getValue($instance);
                }
                $converter = new $className($args);
            }
            elseif (!$converter){
                if ('array' == $type){
                    // Deafults to array of strings
                    $className = 'Akuehnis\SymfonyApi\Converter\StringConverter';
                } else {
                    // For base types we have a converter ready to use
                    $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                }
                $args = [
                    'required' => !$reflection_property->isInitialized($instance),
                    'nullable' => $reflection_named_type ? $reflection_named_type->allowsNull() : false,
                    'name' => $name,
                    'is_array' => 'array' == $type,
                ];
                if ($reflection_property->isInitialized($instance)){
                    $args['default_value'] = $reflection_property->getValue($instance);
                }
                $converter = new $className($args);
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