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
        $obj = $this->resolveClass($classname, $request);

        yield $obj;
    }

    public function resolveClass($classname, $request){
        $obj = new $classname;
        $data = json_decode($request->getContent(), true);
        $reflection = new \ReflectionClass($classname);
        $submitted_data = [];
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $reflection_type = $property->getType();
            $type = $reflection_type->getName();
            $name = $property->getName();
            if (!array_key_exists($name, $data)){
                continue;
            }
            if (!$property->hasDefaultValue() || !is_object($property->getDefaultValue())){
                $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                if ($property->hasDefaultValue()){
                    $converter = new $className(['defaultValue' => $property->getDefaultValue()]);
                } else {
                    $converter = new $className([]);
                }
            } else {
                $converter = $property->getDefaultValue();
            }
            if (isset($data[$name])){
                $converter->value = $data[$name];
                $obj->{$name} = $converter->denormalize();
                $submitted_data[$name] = $obj->{$name};
            }
        }
        $obj->storeSubmittedData($submitted_data);

        return $obj;

    }
}