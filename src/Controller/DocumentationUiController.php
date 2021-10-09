<?php 
namespace Akuehnis\SymfonyApi\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Akuehnis\SymfonyApi\Services\DocBuilder;

class DocumentationUiController extends AbstractController
{
    protected $DocBuilder;

    public function __construct(DocBuilder $DocBuilder)
    {
        $this->DocBuilder = $DocBuilder;
    }

    public function __invoke(Request $request)
    {
        $area = 'default';
        return $this->render('@AkuehnisSymfonyApi/swagger_ui.html.twig', [
            'swagger_data' => $this->DocBuilder->getSpec($area)
        ]);
              
    }
}