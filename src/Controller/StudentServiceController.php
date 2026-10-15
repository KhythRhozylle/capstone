<?php

namespace App\Controller;

use App\Service\StudentServiceCatalog;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StudentServiceController extends AbstractController
{
    public function __construct(private readonly StudentServiceCatalog $catalog)
    {
    }

    #[Route('/sds', name: 'app_sds', defaults: ['code' => 'SDS'], methods: ['GET'])]
    #[Route('/sws', name: 'app_sws', defaults: ['code' => 'SWS'], methods: ['GET'])]
    #[Route('/isps', name: 'app_isps', defaults: ['code' => 'ISPS'], methods: ['GET'])]
    public function show(string $code): Response
    {
        $service = $this->catalog->get($code);
        if (null === $service) {
            throw $this->createNotFoundException('Student service not found.');
        }

        return $this->render('services/show.html.twig', [
            'service' => $service,
            'services' => $this->catalog->all(),
        ]);
    }
}
