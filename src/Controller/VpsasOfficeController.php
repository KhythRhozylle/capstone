<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Public page for the Office of the Vice President for Student Affairs & Services.
 * Kept off the /vpsas prefix, which is the VPSAS staff dashboard.
 */
class VpsasOfficeController extends AbstractController
{
    #[Route('/office-of-the-vice-president', name: 'app_vpsas_office', methods: ['GET'])]
    public function show(): Response
    {
        return $this->render('vpsas/office.html.twig');
    }
}
