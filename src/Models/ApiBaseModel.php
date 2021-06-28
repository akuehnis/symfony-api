<?php
namespace Akuehnis\SymfonyApi\Models;

/**
 * Api Input und Output-Models müssen diese Klasse erweitern
 */
class ApiBaseModel 
{
    private $symfony_api_submitted_data = null;

    public function storeSubmittedData($data){
        $this->symfony_api_submitted_data = $data;
    }

    public function fetchSubmittedData(){
        return $this->symfony_api_submitted_data;
    }

}