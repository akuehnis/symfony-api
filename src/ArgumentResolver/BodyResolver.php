<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Akuehnis\SymfonyApi\Services\DocBuilder;
class BodyResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $DocBuilder;

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (!is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            return false;
        }
        
        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $classname = $argument->getType();
        $obj = new $classname();
        $property_models = $this->DocBuilder->getClassPropertyModels($classname);
        $data = json_decode($request->getContent(), true);
        $submitted_data = [];
        foreach ($data as $key => $val){
            if (isset($property_models[$key])) {
                $type = $property_models[$key]->type;
                if ('DateTime' == $type){
                    $val = new \DateTime($val);
                } else {
                    settype($val, $type);
                }
            }
            $obj->{$key} = $val;
            $submitted_data[$key] = $val;
        }
        $obj->storeSubmittedData($submitted_data);

        yield $obj;
    }
}