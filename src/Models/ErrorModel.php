<?php
namespace Akuehnis\SymfonyApi\Models;


class ErrorModel
{

    /**
     * Location
     */
    public array $loc = [];

    /**
     * Message, info about error
     */
    public string $msg; 
    
}