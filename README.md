# Symfony-API

See https://symfonyapi.akuehnis.com/ for documentation.

### Typehinting defines the openapi model

Symfony-API will only add routes to the documentation where an annotation of
Akuehnis\SymfonyApi\AnnotationsSymfonyApi is present.

For scalar Values (int, bool, string, float, array) there are default converters already present.

For any other value types, converters can be passed with annotations.

As of PHP 8.1, converters can also be passed as objects as  function parameter default value.

```
<?php
// src/Controller/DefaultController.php
namespace App\Controller

use App\Schemas\MyOutputModel;

use App\Converter\DateConverter;

class DefaultController
{
    /**
     * The first line will be the endpoint summary
     * 
     * Further lines will be the endpoint description.
     *
     * @Akuehnis\SymfonyApi\AnnotationsSymfonyApi(tag="abrakadabra")
     * @Route("/hello/{$one}", name="app_hello", methods={"GET"})
     * @DateConverter(property_name="start_date", title="abcd" )
     */
    public function hello(
        string $one
        string $two,
        ?int $three,
        bool $four = false,
        ?float $five = null,

        ?\DateTime $start_date = null,
        ?\DateTime $end_date = new DateConverter(['defaultValue' => null])
    ){
        $model = new MyOutputModel();
        // ... more code
        return $model;
    }

    // ... probably more code

}
```


## Body Model

The model MUST extend Akuehnis\SymfonyApi\Models\ApiBaseModel.

```
<?php
// src/Schemas/MyInputModel.php

namespace App\Schemas;

use Symfony\Component\Validator\Constraints as Assert;
use DateTimeInterface;

use Akuehnis\SymfonyApi\Models\ApiBaseModel; 

class MyInputModel extends  ApiBaseModel {

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

The class of the return model must be passed with annotations.  

If the return Model is a class extending the BaseModel, it will automatically be serialized to Json.


```
<?php
// src/Schemas/MyOutputModel.php

namespace App\Schemas;

use Akuehnis\SymfonyApi\Models\ApiBaseModel;

class MyOutputModel extends ApiBaseModel {
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

use Akuehnis\SymfonyApi\Annotations\Tag as SymfonyApiTag;
use Akuehnis\SymfonyApi\Annotations\ResponseModel as SymfonyApiResponseModel;

use App\Schemas\MyOutputModel;

class DefaultController
{
    /**
     * @Akuehnis\SymfonyApi\AnnotationsSymfonyApi(tag="abrakadabra")
     * @SymfonyApiResponseModel(name="App\Schemas\MyOutputModel")
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

