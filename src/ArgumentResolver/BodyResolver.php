<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Doctrine\Common\Annotations\AnnotationReader;
use Akuehnis\SymfonyApi\Services\ModelService;
use Akuehnis\SymfonyApi\Services\RouteService;

// DEPRICATED, kann wohl gelöscht werden
class BodyResolver implements ArgumentValueResolverInterface
{

    protected $ModelService;
    protected $RouteService;

    public function __construct(ModelService $ModelService, RouteService $RouteService){
        $this->ModelService = $ModelService;
        $this->RouteService = $RouteService;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if ($this->RouteService->isApiRoute($request) && is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            return true;
        }
        
        return false;
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
                $converter = $this->ModelService->getPropertyConverter($property);
                if (!$converter){
                    throw new \Exception("No converter found for " . $name);
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