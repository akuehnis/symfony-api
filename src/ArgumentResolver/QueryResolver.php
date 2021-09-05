<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

use Akuehnis\SymfonyApi\Services\DocBuilder;
use Akuehnis\SymfonyApi\Types\BaseField;
use Akuehnis\SymfonyApi\Types\BaseType;

class QueryResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $DocBuilder;

    private $base_types =  ['string', 'int', 'float', 'bool', 'array'];

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }

    /**
     * check if the parameter type is supported
     */
    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (in_array($type, $this->base_types)) {
            return true;
        }
        if (!$argument->hasDefaultValue()){
            return false;
        }
        $defaultValue = $argument->getDefaultValue();
        if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
            return true;
        }

        return false;
    }

    /**
     * Resolves the controller method parameters
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (!$argument->hasDefaultValue() || !is_object($argument->getDefaultValue())){
            $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
            if ($argument->hasDefaultValue()){
                $converter = new $className(['defaultValue' => $argument->getDefaultValue()]);
            } else {
                $converter = new $className([]);
            }
        } else {
            $converter = $argument->getDefaultValue();
        }
        $value = $request->query->get($argument->getName());
        if (null !== $value){
            $converter->value = $value;
        } 
        yield $converter->denormalize();
    }
}