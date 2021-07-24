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


class QueryResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $DocBuilder;

    private $base_types =  ['string', 'int', 'float', 'bool'];

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
        if (!in_array($type, $this->base_types)) {
            return false;
        }

        return true;
    }

    /**
     * Resolves the controller method parameters
     */
    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return;
        }
        $route = $this->DocBuilder->getRouteByName($routeName);
        $parameter_models = $this->DocBuilder->getRouteParameterModels($route);
        if (isset($parameter_models[$argument->getName()])){
            $val = $request->query->get($argument->getName());
            if (null === $val && $parameter_models[$argument->getName()]->has_default){
                yield $parameter_models[$argument->getName()]->default;
            } else {
                yield $val;
            }
        }
    }
}