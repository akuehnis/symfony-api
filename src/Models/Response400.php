<?php
namespace Akuehnis\SymfonyApi\Models;

use Akuehnis\SymfonyApi\Models\ErrorModel;

class Response400
{
    public string $detail = '';

    /**
     * @var array<Akuehnis\SymfonyApi\Models\ErrorModel>
     */
    public array $errors = [];
    
}