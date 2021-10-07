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

    /**
     * Http Status.
     *
     * @var int
     */
    public $state = 200;


    public function __construct($props){
        if (isset($props['name'])) {
            $this->name = $props['name'];
        }
        if (isset($props['state'])) {
            $this->state = $props['state'];
        }
    }
}