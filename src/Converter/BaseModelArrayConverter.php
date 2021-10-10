<?php
namespace Akuehnis\SymfonyApi\Converter;

use Doctrine\Common\Annotations\AnnotationReader;

/** @Annotation */
class BaseModelArrayConverter extends BaseModelConverter
{

    protected $class_name = '\Akuehnis\SymfonyApi\Models\BaseModel';

    protected array $schema = [
        'type' => 'array'
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

    public function denormalize($data)
    {
        $class_name = $this->getClassName();
        $converters = $this->getPropertyConverters();
        $rows = [];
        foreach ($data as $row){
            if (null === $row){
                $rows[] = null;
            } else {
                $obj = new $class_name();
                foreach ($converters as $converter){
                    $name = $converter->getName();
                    if (isset($row[$converter->getName()])){
                        $value = $converter->denormalize($row[$converter->getName()]);
                    } else {
                        $value = $converter->denormalize($row->getDefaultValue());
                    }
                    $obj->{$name} = $value;
                }
                $rows[] = $obj;
            }
        }

        return $rows;

    }

    public function normalize($data)
    {
        $rows = [];
        foreach ($data as $row) {
            if (null === $row){
                $rows[] = null;
            } else {
                $converter = new BaseModelConverter(['class_name' => get_class($row)]);
                $rows[] = $converter->normalize($row);
            }
        }
        return $rows;
        

    }

    public function getSchema():array 
    {
        $converter = new BaseModelConverter(['class_name' => $this->getClassName()]);
        $class_schema = [
            'type' => 'array',
            'items' => [
                '$ref' => '#/components/schemas/' . $converter->getClassNameShort()
            ]
        ];

        return $class_schema;

    }

    public function validate($data):array
    {
        $errors = [];
        $class_name = $this->getClassName();
        $converters = $this->getPropertyConverters();
        foreach ($data as $i=>$row){
            foreach ($converters as $converter){
                // Todo: the location must include the $i just before the name
                if ($converter->getRequired() && !isset($data[$converter->getName()])) {
                    $errors[] = [
                        'loc' => $converter->getLocation(),
                        'msg' => 'Required',
                    ];
                } else if (!$converter->getNullable() && null === $row[$converter->getName()]){
                    $errors[] = [
                        'loc' => $converter->getLocation(),
                        'msg' => 'Null not allowed',
                    ];
                } else {
                    $violations = $converter->validate($row[$converter->getName()]);
                    if (0 < count($violations)){
                        $errors = array_merge($errors, $violations);
                    }
                }
            }
        }
        return $errors;
    }

}