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
    protected $DocBuilder;
    protected $RouteService;

    public function __construct(DocBuilder $DocBuilder, RouteService $RouteService, ValidatorInterface $Validator)
    {
        $this->DocBuilder = $DocBuilder;
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
        $routeName = $request->attributes->get('_route');
        if (!$routeName){
            return;
        }
        if (!$this->RouteService->isApiRoute($request)){
            return;
        }
        $route = $this->RouteService->getRouteByName($routeName);
        if (!$route){
            // Route is not in the observed range of SymfonyApi
            return;
        }
        $errors_query = $this->validateQuery($request, $route);
        $errors_body = $this->validateBody($request, $route);
        $errors = array_merge($errors_query, $errors_body);
        if (0 < count($errors)){
            $event->setResponse(new JsonResponse([
                'detail' => 'Request validation failed',
                'errors' => $errors,
            ], 400));
        }
    }

    /**
     * Validate path and query parameters
     * 
     * @param Request $request The symfony request object
     * @param Route $route The symfony route object
     * @return array errors
     * 
     */
    public function validateQuery($request, $route)
    {
        $annotationReader = new AnnotationReader();
        $errors = [];
        $reflection = $this->RouteService->getMethodReflection($route);
        $annotations = $annotationReader->getMethodAnnotations($reflection);
        foreach ($reflection->getParameters() as $parameter){
            $name = $parameter->getName();
            $reflection_type = $parameter->getType();
            if (!$reflection_type) {
                continue;
            }
            $type = $reflection_type->getName();
            if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $data = json_decode($request->getContent(), true);
                if (null === $data) {
                    // There seems to be a decoding problem or no content
                    return [
                        [
                            'loc' => ['body'],
                            'msg' => 'Could not parse body',
                            'type' => null,
                        ]
                    ];
                }
                $body_errors = $this->validateBody($type, $data);
                $errors = array_merge($errors, $body_errors);
                continue;
            }
            $converter = null;
            $defaultValue = null;
            if ($parameter->isDefaultValueAvailable()){
                $defaultValue = $parameter->getDefaultValue();
            }
            $converter_annotations = array_filter($annotations, function($item) use ($name) {
                return is_subclass_of($item, 'Akuehnis\SymfonyApi\Converter\ApiConverter')
                    && $item->property_name == $name;
            });
            $converter = array_shift($converter_annotations);
            if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                // even if converter is found in annotations, converters passed as default value must have priority
                $converter = $defaultValue;
            } 
            if (!$converter && in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
                $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                if ($parameter->isDefaultValueAvailable()){
                    $converter = new $className(['defaultValue' => $parameter->getDefaultValue()]);
                } else {
                    $converter = new $className([]);
                }
            } 
            if (!$converter){
                continue;
            }
            $converter->value = $request->get($name);
            $location = ['query'];
            if (false !== strpos($route->getPath(), '{'.$name.'}')){
                $location = ['path']; 
            } 
            $violations = $this->Validator->validate($converter);
            if (0 < count($violations)) {
                foreach ($violations as $violation){
                    // property-Path enthält [property_name]
                    $name =  trim($violation->getPropertyPath(), '[]');
                    $constraint = $violation->getConstraint();
                    $type = get_class($constraint);
                    $errors[] = [
                        'loc' => array_merge($location, [$name]),
                        'msg' => $violation->getMessage(),
                        'type' => $type,
                    ];
                }
            }
        }

        return $errors;
    }

    /**
     * Validates the body of the request
     * @return array errors
     */
    public function validateBody($classname, $data, $location = ['body'])
    {
        $errors = [];
        $annotationReader = new AnnotationReader();
        $obj = new $classname;
        $reflection = new \ReflectionClass($classname);
        $submitted_data = [];
        foreach ($reflection->getProperties() as $property){
            if (!$property->isPublic()){
                continue;
            }
            $reflection_type = $property->getType();
            if (!$reflection_type) {
                continue;
            }
            $type = $reflection_type->getName();
            $name = $property->getName();
            $converter = null;
            if (!array_key_exists($name, $data)){
                continue;
            }
            if (is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
                $sub_errors = $this->validateBody($type, $data[$name], array_merge($location, $name));
                if (0 < count($sub_errors)){
                    $errors = array_merge($errors, $sub_errors);
                }
            } else {
                $annotations = $annotationReader->getPropertyAnnotations($property);
                foreach ($annotations as $annotation){
                    if (is_object($annotation) && is_subclass_of($annotation, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                        $converter = $annotation;
                    }
                }
                if (!$converter){
                    if (in_array($type, ['bool', 'int', 'string', 'float', 'array'])){
                        $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                        if ($property->hasDefaultValue()){
                            $converter = new $className(['defaultValue' => $property->getDefaultValue()]);
                        } else {
                            $converter = new $className([]);
                        }
                    } else {
                        throw new \Exception("No converter found for " . $name);
                    }
                }
                if (isset($data[$name])){
                    $converter->value = $data[$name];
                    $violations = $this->Validator->validate($converter);
                    if (0 < count($violations)) {
                        foreach ($violations as $violation){
                            // property-Path enthält [property_name]
                            $constraint = $violation->getConstraint();
                            $type = get_class($constraint);
                            $errors[] = [
                                'loc' => array_merge($location, [$name]),
                                'msg' => $violation->getMessage(),
                                'type' => $type,
                            ];
                        }
                    }
                }
            }          
        }

        return $errors;
        
    }

    
}