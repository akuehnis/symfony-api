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

use Symfony\Component\Validator\Validator\ValidatorInterface;


class QueryResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $Validator;

    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(ValidatorInterface $Validator)
    {
        $this->Validator = $Validator;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (!in_array($type, $this->base_types)) {
            return false;
        }
        
        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (in_array($type, $this->base_types)){
            $val = $request->query->get($argument->getName());
            if (null === $val && !$argument->isNullable() && $argument->hasDefaultValue()){
                yield $argument->getDefaultValue();
            } else if ('bool' == $type && 'false' == strtolower($val)){
                yield false;
            } else {
                yield $val;
            }
        }       
    }
}