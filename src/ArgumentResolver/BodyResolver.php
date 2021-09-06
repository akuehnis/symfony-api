<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Doctrine\Common\Annotations\AnnotationReader;

class BodyResolver implements ArgumentValueResolverInterface
{

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        if (!$this->RouteService->isApiRoute($request)){
            return false;
        }
        
        $type = $argument->getType();
        if (!is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            return false;
        }
        
        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $classname = $argument->getType();
        $data = json_decode($request->getContent(), true);
        $obj = $this->resolveClass($classname, $data);

        yield $obj;
    }

    public function resolveClass($classname, $data){
        $annotationReader = new AnnotationReader();
        $obj = new $classname();
        $reflection = new \ReflectionClass($classname);
        $submitted_data = [];
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $reflection_type = $property->getType();
            if (!$reflection_type) {
                continue;
            }
            $type = $reflection_type->getName();
            $name = $property->getName();
            $converter = null;
            if (!array_key_exists($name, $data)){
                continue;
            }
            if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $obj->name = $this->resolveClass($type, $data[$name]);
            } else {
                $annotations = $annotationReader->getPropertyAnnotations($property);
                foreach ($annotations as $annotation){
                    if (is_object($annotation) && is_subclass_of($annotation, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                        $converter = $annotation;
                    }
                }
                if (!$converter){
                    if (in_array($type, ['bool', 'int', 'string', 'float', 'array'])){
                        $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                        if ($property->hasDefaultValue()){
                            $converter = new $className(['defaultValue' => $property->getDefaultValue()]);
                        } else {
                            $converter = new $className([]);
                        }
                    } else {
                        throw new \Exception("No converter found for " . $name);
                    }
                }
                if (isset($data[$name])){
                    $converter->value = $data[$name];
                    $obj->{$name} = $converter->denormalize();
                    $submitted_data[$name] = $obj->{$name};
                }
            }
        }
        $obj->storeSubmittedData($submitted_data);

        return $obj;

    }
}