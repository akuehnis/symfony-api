<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Serializer;


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
        $value = $event->getControllerResult();
        if (is_object($value) && is_subclass_of($value, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            $encoders = [new JsonEncoder()];
            // Achtung, Reihenfolge der Serializer ist entscheidend!
            $normalizers = [
                new DateTimeNormalizer(),
                new ObjectNormalizer()
            ];
            $serializer = new Serializer($normalizers, $encoders);
            $jsonContent = $serializer->serialize($value, 'json');
            $response = new Response($jsonContent, 200, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        }
    }


}