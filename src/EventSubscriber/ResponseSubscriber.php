<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Akuehnis\SymfonyApi\Converter\ObjectConverter;


class ResponseSubscriber implements EventSubscriberInterface
{

    protected $RouteService;

    public function __construct(
        \Akuehnis\SymfonyApi\Services\RouteService $RouteService
    ){
        $this->RouteService = $RouteService;
    }

    public static function getSubscribedEvents()
    {
        // return the subscribed events, their methods and priorities
        return [
            'kernel.view' => 'onKernelView',
            //'kernel.finish_request' => 'onFinishRequest'
        ];
    }

    public function onKernelView($event)
    {
        $request = $event->getRequest();
        $value = $event->getControllerResult();
        $class_name = null;
        if (is_object($value)){
            $class_name = get_class($value);
        } else if (is_array($value) && is_object($value[0])){
            $class_name = get_class($value[0]);
        }
        $route = $this->RouteService->getRouteFromRequest($request);
        if ($class_name && 'Akuehnis\SymfonyApi\Models\Response400' == $class_name){
            $converter = new ObjectConverter([
                'class_name' => $class_name, 
                'is_array' => false
            ]);
            $output = $converter->normalize($value);
            $jsonContent = json_encode($output);
            $response = new Response($jsonContent, 400, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        }
        else if ($route && $class_name){
            $annotations = $this->RouteService->getRouteAnnotations($route);
            foreach ($annotations as $annotation){
                if (
                    'Akuehnis\SymfonyApi\Annotations\Response' == get_class($annotation) 
                    && $annotation->class_name == $class_name
                ){
                    $is_array = is_array($value);
                    $status = $annotation->status;
                    $converter = new ObjectConverter([
                        'class_name' => $annotation->class_name, 
                        'is_array' => $is_array
                    ]);
                    $output = $converter->normalize($value);
                    $jsonContent = json_encode($output);
                    $response = new Response($jsonContent, $status, ['Content-Type' => 'application/json']);
                    $event->setResponse($response);
                }
            }
        }
    }

}