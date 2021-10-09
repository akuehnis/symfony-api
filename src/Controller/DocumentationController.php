<?php 
namespace Akuehnis\SymfonyApi\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Doctrine\Common\Annotations\AnnotationReader;

use Akuehnis\SymfonyApi\Services\DocBuilder;

class DocumentationController 
{
    protected $DocBuilder;

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $area = 'default';
        return new JsonResponse($this->DocBuilder->getSpec($area));
    }
}