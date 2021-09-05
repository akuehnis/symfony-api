<?php

namespace Akuehnis\SymfonyApi\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;
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
        $route = $this->DocBuilder->getRouteByName($routeName);
        if (!$route){
            // Route is not in the observed range of SymfonyApi
            return;
        }
        $errors_path = $this->validatePath($request, $route);
        $errors_query = $this->validateQuery($request, $route);
        $errors_body = $this->validateBody($request, $route);
        $errors = array_merge($errors_path, $errors_query, $errors_body);
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
    public function validatePath($request, $route)
    {
        $parameter_definitions = $this->DocBuilder->getRouteParameterModels($route);
        $input = [];
        $constraints = [];
        foreach ($parameter_definitions as $definition){
            if ('path' != $definition->location){
                continue;
            }
            $name = $definition->name;
            $input[$name] = $request->get($definition->name);
            $constraints[$name] = $this->getConstraints($definition);
        }
        $errors = $this->validate($input, $constraints, ['path']);
        
        return $errors;
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
        $errors = [];
        $reflection = $this->RouteService->getMethodReflection($route);
        foreach ($reflection->getParameters() as $parameter){
            $name = $parameter->getName();
            $reflection_type = $parameter->getType();
            if (!$reflection_type) {
                continue;
            }
            $type = $reflection_type->getName();
            $converter = null;
            $defaultValue = null;
            if ($parameter->isDefaultValueAvailable()){
                $defaultValue = $parameter->getDefaultValue();
            }
            if (is_object($defaultValue) && is_subclass_of($defaultValue, 'Akuehnis\SymfonyApi\Converter\ApiConverter')){
                $converter = $defaultValue;

            } elseif (in_array($type, ['bool', 'string', 'int', 'float', 'array'])){
                $className = 'Akuehnis\SymfonyApi\Converter\\' . ucfirst($type).'Converter';
                $converter = new $className(['defaultValue' => $parameter->getDefaultValue()]);
            } else {
                continue;
            }
            $converter->value = $request->get($name);
            $violations = $this->Validator->validate($converter);
            if (0 < count($violations)) {
                foreach ($violations as $violation){
                    // property-Path enthält [property_name]
                    $name =  trim($violation->getPropertyPath(), '[]');
                    $constraint = $violation->getConstraint();
                    $type = get_class($constraint);
                    $errors[] = [
                        'loc' => array_merge(['query'], [$name]),
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
     * 
     * This will deserialize the request body into the target object. 
     * Todo: store the objet in the request object so that we don't have
     * to unserialize again int the BodyResolver
     * 
     * @param Request $request The symfony request object
     * @param Route $route The symfony route object
     * @return array errors
     */
    public function validateBody($request, $route)
    {
        $body_definition = null;
        $parameter_definitions = $this->DocBuilder->getRouteParameterModels($route);
        foreach ($parameter_definitions as $definition){
            if ('body' == $definition->location){
                $body_definition = $definition;
                break;
            }
        }
        if (null === $body_definition){
            // There is no body model defined
            return [];
        }
        $classname = $body_definition->type;
        $properties = $this->DocBuilder->getClassPropertyModels($classname);
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
        $constraints = [];
        foreach ($properties as $property){
            $constraints[$property->name] = $this->getConstraints($property);
        }

        $errors = $this->validate($data, $constraints, ['body']);
        return $errors;
        
    }

    /**
     * Returns validation constraints for a paramodel
     * 
     * @param ParaModel $model 
     * @return Constraint[]
     *
     */
    public function getConstraints($model)
    {
        $arr = [];
        if (!$model->is_nullable && !$model->has_default){
            $arr[] = new Assert\NotNull();
        } else if (!$model->is_nullable && $model->required) {
            $arr[] = new Assert\NotBlank();
        }
        if ('int' == $model->type){
            $arr[] = new Assert\Regex([
                "pattern" => '/^[0-9]+/',
                'message' => 'This value should be of type int'
            ]);
        }
        if ('float' == $model->type){
            $arr[] = new Assert\Regex([
                "pattern" => '/^[0-9]+(\.[0-9]+)?/',
                'message' => 'This value should be of type float'
            ]);
        }
        if (in_array($model->type, ['string', 'array'])){
            $arr[] = new Assert\Type([
                "type" => $model->type
            ]);
        }
        if (in_array($model->type, ['bool'])){
            $arr[] = new Assert\Choice(['true', 'false', '0', '1']);
        }
        if ('DateTime' == $model->type){
            // Format default is Y-m-d H:i:s, see https://symfony.com/doc/current/reference/constraints/DateTime.html
            $arr[] = new Assert\DateTime();
        }
        return $arr;
    }

    /**
     * validate data array against constraints
     * 
     * @param array $data associative array
     * @param array $constraints associative array
     * @param array $location will be returned as loc of the error extended with name
     * @return arrray errors
     */
    public function validate($data, $constraints, $location = []) 
    {
        $validator = Validation::createValidator();
        $violations = $validator->validate($data, new Assert\Collection($constraints));
        $errors = [];
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

        return $errors;

    }
}