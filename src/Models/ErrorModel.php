<?php
namespace Akuehnis\SymfonyApi\Models;


class ErrorModel
{

    /**
     * Location
     * @var array<string> Parts of location
     */
    public array $loc;

    /**
     * Message, info about error
     */
    public string $msg; 

    /**
     * Type of error
     */
    public ?string $type = null;
    
}