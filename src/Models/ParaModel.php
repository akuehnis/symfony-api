<?php
namespace Akuehnis\SymfonyApi\Models;


class ParaModel
{
    public string $name;
    
    public string $description = ''; // darf nie null sein

    public ?string $location = null;

    public ?bool $required = null;

    public ?bool $is_nullable = null;

    public ?bool $has_default = null;

    public $default = null;

    public $type = null;

    public ?string $format = null;

    public $items = null;

}