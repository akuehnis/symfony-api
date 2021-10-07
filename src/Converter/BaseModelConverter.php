<?php
namespace Akuehnis\SymfonyApi\Converter;

use Symfony\Component\Validator\Constraints as Assert;

/** @Annotation */
class BaseModelConverter extends ValueConverter
{

    /**
     * Todo: Validiert wird nicht das Objekt selbst, sondern die Werte in deren 
     * Convertern
     * 
     * Value wäre hier also wohl am besten ein Std-Objekt
     * 
     * @Assert\Valid
     */
    public $value;

    private $class_name = '\Akuehnis\SymfonyApi\Models\ApiBaseModel';

    private array $schema = [
        'type' => 'object'
    ];

    public function __construct($params = [])
    {
        parent::__construct($params);
        if (isset($params['class_name'])){
            $this->setClassName($params['class_name']);
        }
    }

    public function denormalize(){
        return (string)$this->value;
    }

    public function normalize($value){
        $this->value = (string) $value;
    }

    public function setClassName($class_name){
        $this->class_name = $class_name;
    }

    public function getClassName(){
        return $this->class_name;
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
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $name = $property->getName();
            $reflection_named_type = $property->getType();
            $type = $reflection_named_type->getName();
            $annotationReader = new AnnotationReader();
            $annotations = $annotationReader->getPropertyAnnotations($reflection);
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
                if ($property->isInitialized($instance)){
                    $converter = new $className([
                        'default_value' => $property->getValue($instance),
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
            if (!$converter && is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $className = 'Akuehnis\SymfonyApi\Converter\BaseModelConverter';
                if ($property->isInitialized($instance)){
                    $converter = new $className([
                        'default_value' => $property->getValue($instance),
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

    public function validate():array
    {

    }
}