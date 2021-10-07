<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\Common\Annotations\AnnotationReader;
use Akuehnis\SymfonyApi\Services\DocBuilder;
use Akuehnis\SymfonyApi\Services\RouteService;

class RequestValidationSubscriber implements EventSubscriberInterface
{
    protected $RouteService;

    public function __construct(RouteService $RouteService, ValidatorInterface $Validator)
    {
        $this->RouteService = $RouteService;
        $this->Validator = $Validator;
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
        $route = $this->RouteService->getRouteFromRequest($request);
        if (!$route){
            return;
        }
        $param_converters = $this->RouteService->getRouteParamConverters($route);
        if (0 == count($param_converters)){
            return;
        }
        $errors = [];
        $values = $request->query->all();
        $path_values = $request->attributes->all();
        foreach ($param_converters as $converter){
            $name = $converter->getName();
            $location = $converter->getLocation();
            if ('query' == array_shift($location)){
                if ($converter->getRequired() && !isset($values[$name])){
                    $errors[] = [
                        'loc' => array_merge($converter->getLocation(), [$name]),
                        'msg' => 'Required',
                    ];
                    continue;
                } else if (isset($values[$name])){
                    $converter->setValue($values[$name]);
                }
            } else if ('path' == array_shift($location)){
                $converter->setValue($path_values[$name]);
            } else if  ('body' == array_shift($location)){
                die('da');
                $data = json_decode($request->getContent(), true);
                $converter->setValue($data);
            }
            $violations = $converter->validate();
            if (0 < count($violations)) {
                var_dump($location);
                foreach ($violations as $error){
                    $errors[] = [
                        'loc' => array_merge($location, [$name]),
                        'msg' => $error,
                    ];
                }
            }
        }
        if (0 < count($errors)){
            $event->setResponse(new JsonResponse([
                'detail' => 'Request validation failed',
                'errors' => $errors,
            ], 400));
        }
    }
}