<?php
namespace Akuehnis\SymfonyApi\Models;

/**
 * Api Input und Output-Models müssen diese Klasse erweitern
 */
Trait ApiBaseTrait
{
    private $symfony_api_submitted_data = null;

    public function symfonyApiStoreSubmittedData($data){
        $this->symfony_api_submitted_data = $data;
    }

    public function symfonyApiFetchSubmittedData(){
        return $this->symfony_api_submitted_data;
    }

}