<?php
namespace Akuehnis\SymfonyApi\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;

use Symfony\Component\Validator\Validator\ValidatorInterface;

class BodyResolver implements ArgumentValueResolverInterface
{
    private $security;
    private $Validator;
    private $base_types =  ['string', 'int', 'float', 'bool'];

    public function __construct(ValidatorInterface $Validator)
    {
        $this->Validator = $Validator;
    }

    public function supports(Request $request, ArgumentMetadata $argument)
    {
        $type = $argument->getType();
        if (!is_subclass_of($type, 'Akuehnis\SymfonyApi\Models\ApiBaseModel')){
            return false;
        }
        
        return true;
    }

    public function resolve(Request $request, ArgumentMetadata $argument)
    {
        $classname = $argument->getType();
        $obj = new $classname();
        $data = json_decode($request->getContent(), true);
        $submitted_data = [];
        if (null === $data){
            http_response_code(400);
            header('Conent-Type:application/json');
            echo json_encode(json_encode(['detail' => 'Could not parse body']));
            die();
        }

        https://symfony.com/doc/current/components/property_info.html
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $listExtractors = [$reflectionExtractor];
        $typeExtractors = [$phpDocExtractor, $reflectionExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $accessExtractors = [$reflectionExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];

        $propertyInfo = new PropertyInfoExtractor(
            $listExtractors,
            $typeExtractors,
            $descriptionExtractors,
            $accessExtractors,
            $propertyInitializableExtractors
        );
        foreach ($data as $key => $val){
            if (!property_exists($obj, $key)){
                continue;
            }
            $types = $propertyInfo->getTypes($classname, $key);
            $type = array_shift($types);
            if ($type) {
                if (in_array($type->getBuiltinType(), $this->base_types)){
                    settype($val, $type->getBuiltinType());
                } else if ('DateTime' == $type->getClassName()){
                    $val = new \DateTime($val);
                }
            }
            $obj->{$key} = $val;
            $submitted_data[$key] = $val;
        }

        $obj->storeSubmittedData($submitted_data);

        $errors = $this->Validator->validate($obj);

        if (0 < count($errors)){
            $error_data = [];
            foreach ($errors as $error){
                $property_path = $error->getPropertyPath();
                if (!isset($error_data[$property_path])) {
                    $error_data[$property_path] = [];
                }
                $error_data[$property_path][] = $error->getMessage();
            }
            http_response_code(400);
            header('Content-Type:application/json');
            echo json_encode(json_encode([
                'detail' => 'Bad Request',
                'errors' => $error_data,
            ]));
            die();
        }

        yield $obj;
    }
}