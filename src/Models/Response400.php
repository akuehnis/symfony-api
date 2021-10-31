<?php
namespace Akuehnis\SymfonyApi\Models;

use Akuehnis\SymfonyApi\Models\ErrorModel;
use Akuehnis\SymfonyApi\Converter\BaseModelConverter;

class Response400 extends BaseModel
{
    public string $detail = '';

    /**
    * @BaseModelConverter(is_array=true, name="errors", description="errors", required=false, nullable=false, class_name="Akuehnis\SymfonyApi\Models\ErrorModel" )
    **/
    public array $errors = [];
    
}