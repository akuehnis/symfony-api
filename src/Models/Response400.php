<?php
namespace Akuehnis\SymfonyApi\Models;

use Akuehnis\SymfonyApi\Converter\ObjectConverter;

class Response400
{
    public string $detail = '';

    /**
    * @ObjectConverter(is_array=true, name="errors", description="errors", required=false, nullable=false, class_name="Akuehnis\SymfonyApi\Models\ErrorModel" )
    **/
    public array $errors = [];
    
}