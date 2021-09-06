<?php
namespace Akuehnis\SymfonyApi\Annotations;

/**
 * @Annotation
 *
 * A "Tag Object": will be used for documentation
 */
class ResponseModel {

    /**
     * The name of the tag.
     *
     * @var string
     */
    public $name;

    public function __construct($props){
        if (isset($props['name'])) {
            $this->name = $props['name'];
        }
    }
}