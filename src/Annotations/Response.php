<?php
namespace Akuehnis\SymfonyApi\Annotations;

/**
 * @Annotation
 *
 * A "Response Object": will be used for documentation
 */
class Response {

    /**
     * The status code of the response.
     *
     * @var int
     */
    public int $status;

    /**
     * Converter class, fully qualified name.
     *
     * @var string
     */
    public string $class_name;

    public function __construct($props){
        if (isset($props['status'])) {
            $this->status = $props['status'];
        }
        if (isset($props['class_name'])) {
            $this->class_name = $props['class_name'];
        }
    }
}