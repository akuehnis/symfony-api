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
            $output = $this->normalize($value);
            $jsonContent = json_encode($output);
            $response = new Response($jsonContent, 200, ['Content-Type' => 'application/json']);
            $event->setResponse($response);
        }
        
    }

    private function normalize($value){
        $classname = get_class($value);
        $class_property_models = $this->DocBuilder->getClassPropertyModels($classname);
        $output = [];
        foreach ($class_property_models as $name=>$property){
            $type = $property->type;
            $val = $value->{$name};
            if (in_array($type, ['bool', 'int', 'string', 'float'])){
                settype($val, $type);
            } else if (is_object($val) && is_subclass_of($val, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $val = $this->normalize($val);
            } else if ('array' == $type) {
                foreach ($val as $i => $row){
                    if (is_object($row) && is_subclass_of($row, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                        $val[$i] = $this->normalize($row);
                    } else if (null !== $property->items && in_array($property->items->type, ['bool', 'int', 'string', 'float'])) {
                        settype($row, $property->items->type);
                        $val[$i] = $row;
                    }
                }
            }
            $output[$name] = $val;
        }
        return $output;
    }


}