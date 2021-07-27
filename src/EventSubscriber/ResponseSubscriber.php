<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Akuehnis\SymfonyApi\Services\DocBuilder;

class ResponseSubscriber implements EventSubscriberInterface
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
            'kernel.view' => 'onKernelView',
            //'kernel.finish_request' => 'onFinishRequest'
        ];
    }

    public function onKernelView($event)
    {
        $request = $event->getRequest();
        $value = $event->getControllerResult();
        if (is_object($value) && is_subclass_of($value, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            $classname = get_class($value);
            $class_property_models = $this->DocBuilder->getClassPropertyModels($classname);
            $output = [];
            foreach ($class_property_models as $name=>$property){
                $type = $property->type;
                if ('DateTime' == $type){
                    $output[$name] = $value->{$name}->format('c');
                } else {
                    $output[$name] = $value->{$name};
                }
            }
            $jsonContent = json_encode($output);
            $response = new Response($jsonContent, 200, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        }
    }


}