<?php
namespace Akuehnis\SymfonyApi\Models;

use Akuehnis\SymfonyApi\Models\ErrorModel;
use Akuehnis\SymfonyApi\Converter\BaseModelArrayConverter;

class Response400
{
    public string $detail = '';

    /**
    * @BaseModelArrayConverter(name="errors", description="errors", required=false, nullable=false, class_name="Akuehnis\SymfonyApi\Models\ErrorModel" )
    **/
    public array $errors = [];
    
}