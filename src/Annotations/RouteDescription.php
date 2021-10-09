<?php
namespace Akuehnis\SymfonyApi\Annotations;

/**
 * @Annotation
 *
 * 
 */
class RouteDescription {

    /**
     * The name of the tag.
     *
     */
    public $summary = '';

    /**
     * The name of the tag.
     *
     */
    public $description = '';

    public function __construct($props){
        if (isset($props['summary'])) {
            $this->summary = $props['summary'];
        }
        if (isset($props['description'])) {
            $this->description = $props['description'];
        }
    }
}