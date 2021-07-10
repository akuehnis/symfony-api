<?php
namespace Akuehnis\SymfonyApi\Models;


class ParaModel
{
    public $name;
    
    public $description = ''; // darf nie null sein

    public $location;

    public $required;

    public $has_default;

    public $default;

    public $type;

    public $format;

    public $items;
}