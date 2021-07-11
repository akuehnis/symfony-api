<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;

use Akuehnis\SymfonyApi\Services\DocBuilder;

class RequestValidationSubscriber implements EventSubscriberInterface
{

    protected $DocBuilder;

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }
    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return [
            'kernel.request' => 'onKernelRequest',
        ];
    }

    public function onKernelRequest($event)
    {
        $request = $event->getRequest();
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return;
        }
        $route = $this->DocBuilder->getRouteByName($routeName);
        if (!$route){
            return;
        }

        $parameter_models = $this->DocBuilder->getParameterModels($route);
        $errors = [];
        foreach ($parameter_models as $model){
            $val = $request->get($model->name);
            if (null === $val && !$model->is_nullable && !$model->has_default) {
                $errors[$model->name][] = 'This value must not be null';
            } else if (null === $val && 'query' == $model->location && $model->required){
                $errors[$model->name][] = 'Parameter required in query';
            } else if (null !== $val && in_array($model->type, ['float', 'int', 'string', 'array'])){
                settype($val, $model->type);
                if ($request->get($model->name) !== $val){
                    $errors[$model->name][] = 'Parameter is not ' . $model->type;
                }
            }
        }
        
        if (0 < count($errors)) {
            $event->setResponse(new JsonResponse([
                'detail' => 'Bad request',
                'errors' => $errors,
            ], 400));
        }
    }


}