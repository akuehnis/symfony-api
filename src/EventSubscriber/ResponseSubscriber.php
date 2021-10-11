<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Akuehnis\SymfonyApi\Converter\BaseModelConverter;

class ResponseSubscriber implements EventSubscriberInterface
{

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
        if (is_object($value) && is_subclass_of($value, 'Akuehnis\SymfonyApi\Models\BaseModel')){
            $converter = new BaseModelConverter(['class_name' => get_class($value)]);
            $output = $converter->normalize($value);
            $jsonContent = json_encode($output);
            $response = new Response($jsonContent, 200, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        } else if (is_array($value) && is_object($value[0]) && is_subclass_of($value[0], 'Akuehnis\SymfonyApi\Models\BaseModel')){
            $converter = new BaseModelConverter(['class_name' => get_class($value[0]), 'is_array' => true]);
            $output = $converter->normalize($value);
            $jsonContent = json_encode($output);
            $response = new Response($jsonContent, 200, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        } 
    }

}