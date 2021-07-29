<?php
namespace Akuehnis\SymfonyApi\Annotations;

/**
 * @Annotation
 *
 * A "Tag Object": will be used for documentation
 */
class SymfonyApi {

    /**
     * The name of the tag.
     *
     * @var string
     */
    public $tag;

    public function __construct($props){
        if (isset($props['tag'])) {
            $this->tag = $props['tag'];
        }
    }
}