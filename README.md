# Symfony API

Symfony API allows to build and document APIs.

The project is inspired by https://fastapi.tiangolo.com/ (Python) and NelmioApiDocBundle (https://github.com/nelmio/NelmioApiDocBundle).

The basic idea: Describe your API Endpoints and path variables as usual Symfony routes. 
Additional query parameters, input and output model are described using PHP Type hinting.

Symfony-API automatically validates input model and returns 400 error if invalid.

## Installation

```
// config/bundles.php

return [
    //...
    Akuehnis\SymfonyApi\AkuehnisSymfonyApi::class => ['all' => true],
    //...
];

```

### Openapi UI

To see swagger - ui

```
# config/routes.yaml
app.swagger_ui:
    path: /api/doc
    methods: GET
    defaults: { _controller: akuehnis.symfony_api.controller.doc_ui }

```

If not visble, then maybe assets were not installed yet:

```
bin/console assets:install
```

### Openapi JSON

To get json:
```
# config/routes.yaml
app.swagger:
    path: /api/doc.json
    methods: GET
    defaults: { _controller: akuehnis.symfony_api.controller.doc_json }
```

## Query parameter

If a parameter is not defined in the path and is of type float, int, bool or string, 
it will be inserted by SymfonyApi automatically.

Set a default value.

```
/**
  * @Route("/testfloatdefault", name="app_testfloatdefault", methods={"GET"})
*/
public function testfloat(float $number = 22.45): Response
{
    /* 
    ...
    */
}
```

Allow a parameter to be NULL by setting default value to NULL.

```
/**
  * @Route("/testfloatnull", name="app_testfloatnull", methods={"GET"})
*/
public function testfloat(float $number = NULL): Response
{
    /* 
    ...
    */
}
```

If a parameter may not be null, then do not add a default value.

```
/**
  * @Route("/testfloat", name="app_testfloat", methods={"GET"})
*/
public function testfloat(float $number): Response
{
    /* 
    ...
    */
}
```

### Boolean Values

Symfony-Api does treat the words 'false' and 'yes' (upper or lower case) as false. 

All other values depend on how PHP converts a value to boolean. 

Usually, empty string and '0' are converted to 'false', all other values to 'true'.


## Body Parameters

The model MUST extend ApiBaseModel

```
<?php
// src/Schemas/MyInputModel.php

namespace App\Schemas;

use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;

use Akuehnis\SymfonyApi\Models\ApiBaseModel;

class MyInputModel extends ApiBaseModel {

    public function __construct(){
        $this->updated = new \DateTime();
    }

    /**
     * @Assert\NotBlank
     */
    public string $name;

    /**
     * @Assert\NotBlank
     */
    public int $counter;

}
```

In the controller, add an Argument type hinted with your Input Model. Body will automatically be
parsed and validated.

```
<?php
// /Controller/DefaultController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Schemas\MyInputModel;

class DefaultController
{
    /**
     * @Route("/testpatch/{id}", name="app_testpatch", methods={"PATCH"})
     */
    public function testpatch(int $id, MyInputModel $model): Response
    {
        // $model will be of type MyInputModel
        // If you are interested just in the submitted data, use method fetchSubmittedData()

        return new JsonResponse($model->fetchSubmittedData());

    }
}

```

## Return Model

If the return class extends Akuehnis\SymfonyApi\Models\ApiBaseModel, this model will 
be documented in Openapi.


```
<?php
// src/Schemas/MyOutputModel.php

namespace App\Schemas;

use Akuehnis\SymfonyApi\Models\ApiBaseModel;

class MyOutputModel extends ApiBaseModel {

    public function __construct(){
        $this->updated = new \DateTime();
    }

    public string $name;

}
```

```
<?php
// /Controller/DefaultController.php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

use App\Schemas\MyOutputModel;

class DefaultController
{
    /**
     * @Route("/testoutput, name="app_testoutput", methods={"GET"})
     */
    public function testoutput(int $id, MyInputModel $model): MyOutputModel
    {
        $out = new MyOutputModel();
        $out->name= "Peter";
        return $out;

    }
}

```


