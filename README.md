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

If Swagger-UI is required, add a route of your choice in your routes.yaml 
with a controller pointing to akuehnis.symfony_api.controller.doc_ui.

Example:

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

If Openapi JSON is required, add a route of your choice in your routes.yaml 
with a controller pointing to akuehnis.symfony_api.controller.doc_json. 

Example: 

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

### Typehinting and Php Docblock define the Openapi Models

Symfony-API will only add routes to the documentation where an annotation of
Akuehnis\SymfonyApi\AnnotationsSymfonyApi is present.

The following code snipped shows two controller functions. Both of them will be processed by 
Symfony-API, however, only the first will be in the documentation because it has the 
Tag annotation.

For documentation, PHPDoc-Comments will be used (see exmple below).

```
<?php
// src/Controller/DefaultController.php
namespace App\Controller

use App\Schemas\MyOutputModel;

class DefaultController
{
    /**
     * The first line will be the endpoint summary
     * 
     * Further lines will be the endpoint description.
     *
     * @Akuehnis\SymfonyApi\AnnotationsSymfonyApi(tag="abrakadabra")
     * @Route("/hello/{$one}", name="app_hello", methods={"GET"})
     * @param string $one Path parameter
     * @param string $two Query parameter, required, not null
     * @param int $three Query parameter, required, nullable
     * @param bool $four Query parameter, not required, not null
     * @param float $five Query parameter, nor required, nullable
     * @return \App\Schemas\OutputModel The output model
     */
    public function hello(
        string $one
        string $two,
        ?int $three,
        bool $four = false,
        ?float $five = null
    ){
        $model = new MyOutputModel();
        // ... more code
        return $model;
    }

    // ... probably more code

}
```


## Body Model

The model MUST use ApiBaseTrait

```
<?php
// src/Schemas/MyInputModel.php

namespace App\Schemas;

use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;

use Akuehnis\SymfonyApi\Models\ApiBaseTrait;

class MyInputModel  {

    use ApiBaseTrait;


    /**
     * @var string $name Required property, not null
     */
    public string $name;

    /**
     * @var int $prio not required property with default value, nullable
     */
    public ?int $counter = 33;

}
```

In the controller, add an Argument type hinted with your Input Model. 
Body will automatically beparsed and validated.

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
     * @Akuehnis\SymfonyApi\AnnotationsSymfonyApi(tag="abrakadabra")
     * @Route("/testpatch/{id}", name="app_testpatch", methods={"PATCH"})
     * @Param MyInputModel $model 
     */
    public function testpatch(int $id, MyInputModel $model)
    {

        //... do something with the model

    }
}

```

## Return Model

If the return class must be documented in the PHPDocBlock, not as a return typehint. 
The reason for this is that the returned type may be different.

If the return Model is a class extending the BaseModel, it will automatically be serialized to Json.


```
<?php
// src/Schemas/MyOutputModel.php

namespace App\Schemas;

use Akuehnis\SymfonyApi\Models\ApiBaseTrait;

class MyOutputModel {
    use ApiBaseTrait;
    /**
     * @var string $name The name property
     */
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
     * @Akuehnis\SymfonyApi\AnnotationsSymfonyApi(tag="abrakadabra")
     * @Route("/testoutput, name="app_testoutput", methods={"GET"})
     * @Return MyOutputModel Define the output model here
     */
    public function testoutput(int $id, MyInputModel $model)
    {
        $out = new MyOutputModel();
        $out->name= "Peter";
        return $out;

    }
}

```

