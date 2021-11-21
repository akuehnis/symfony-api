<?php
namespace Akuehnis\SymfonyApi\Annotations;

/**
 * @Annotation
 *
 * A "Body": will be used for documentation
 */
class Body {

    /**
     * The name of the parameter.
     *
     * @var string
     */
    public string $name;

    /**
     * The status code of the response.
     *
     * @var int
     */
    public bool $is_array = false;

    /**
     * Converter class, fully qualified name.
     *
     * @var string
     */
    public ?string $class_name = null;

    public function __construct($props){
        if (isset($props['is_array'])) {
            $this->is_array = $props['is_array'];
        }
        if (isset($props['name'])) {
            $this->name = $props['name'];
        }
        if (isset($props['class_name'])) {
            $this->class_name = $props['class_name'];
        }
    }

    public function getName():string
    {
        return $this->name;
    }

    public function getClassName():?string
    {
        return $this->class_name;
    }

    public function isArray():bool
    {
        return $this->is_array;
    }
}