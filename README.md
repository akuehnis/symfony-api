# Symfony-API

Symfony-API allows to build and document APIs.

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

### Configuration

```
# config/packages/akuehnis_symfony_api.yaml
akuehnis_symfony_api:
    documentation:
        servers: 
            - url: https://api.example.com/v1
            - url: http://api.example.com/v1
        info:
            title: API Tile
            description: API Description
            version: 1.0.0
        components: 
            securitySchemes:
                api_key:
                    type: apiKey
                    name: X-API-KEY
                    in: header
        security:
            - api_key: [] 
```

Clear the cache after making any changes.

### Add an URL to the Openapi Documentation

Symfony-API will only add routes to the documentation where an annotation of
Akuehnis\SymfonyApi\Annotations\Tag is present.

The following code snipped shows two controller functions. Both of them will be processed by 
Symfony-API, however, only the first will be in the documentation because it has the 
Tag annotation.

For documentation, PHPDoc-Comments will be used (see exmple below).

```
<?php
// src/Controller/DefaultController.php
namespace App\Controller

use Akuehnis\SymfonyApi\Annotations\Tag as DocuTag;
use App\Schemas\MyOutputModel;

class DefaultController
{
    /**
     * The first line will be the endpoint summary
     * 
     * Further lines will be the endpoint description.
     *
     * @DocuTag(name="abrakadabra")
     * @Route("/hello/{name}", name="app_hello", methods={"GET"})
     * 
     * @param string $name This is the description for the parameter 'name'
     * @param string $number This is the description of the parameter 'number'
     * @return This is description of the return value
     */
    public function hello(string $name, int $number = 25): MyOutputModel
    {
        $model = new MyOutputModel();
        $model->name = $name;
        $model->number = $number;
        return $model;
    }

    /**
     * @Route("/notindoku/{name}", name="app_notindoku", methods={"GET"})
     */
    public function notindoku(string $name, int $number = 25): MyOutputModel
    {
        $model = new MyOutputModel();
        $model->name = $name;
        $model->number = $number;
        return $model;
    }

}
```

## Query parameter

If a controller function parameter is not defined in the path and is of type string, float, int or bool,
it will be validated and injected by SymfonyApi automatically.

```
/**
  * @Route("/testhello/{group}", name="app_testhello", methods={"GET"})
*/
public function testhello(string $group, string $search): Response
{
    /* 
       Example:

       GET /testhello/customers?search=Peter

       $group will contain string 'customers'
       $search will contain string 'Peter' 
    */
}
```


### Query parameter with default value

If a query parameter shall be optional, set a default value.

```
/**
  * @Route("/testfloatdefault", name="app_testfloatdefault", methods={"GET"})
*/
public function testfloat(float $number = 22.45): Response
{
    /* 
       if $number is not passed a query parameter it will be 22.45. 
    */
}
```

### Nullable query parameter
Allow a parameter to be NULL by setting default value to NULL.

```
/**
  * @Route("/teststringnull", name="app_teststringnull", methods={"GET"})
*/
public function teststringnull(string $myname = NULL): Response
{
    /* 
        $myname can be string or null.
    */
}
```

### Required query parameter

If a parameter is required, then do not preset neither a default value nor NULL.

```
/**
  * @Route("/testrequired", name="app_testrequired", methods={"GET"})
*/
public function testrequired(int $quantity): Response
{
    /* 
       $quantity must be set. If it is not present in the url, then a 400 error will be returned.
    */
}
```


### Boolean query parameter

Symfony-Api does treat the following values as 'false':
    * false
    * 0
    * empty string

All other values will be 'true'

```
/**
  * @Route("/testbool", name="app_testbool", methods={"GET"})
*/
public function testbool(bool $activated = true): Response
{
    /* 
       /testbool?activated=true: true
       /testbool?activated=1:    true
       /testbool?activated=null: true
       /testbool?activated=:     false
       /testbool?activated=0:    false
       /testbool?activated=false:false
    */
}
```


## Body Model

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


## Type declarations (Type-Hinting or PHPDocBlock)

By default, Symfony-API uses Type hints to find out what type of variable is required. With one exception:

If the type is an array, then PHP does only allow to typehint 'array' without further description what the array
contains. In these cases, Symfony-API parses the PHPDoc. This allows to specify array contents.

```
@return MyOutputModel[] Array of output models

or 

@return array<MyOutputModel> Array of output models
```

In all other cases Symfony-API uses type hint information for validation. However, if available, PHPDoc type information
is used for documentation.

