<?php

namespace App\Controller;

use App\Service\DashboardStats;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/vpsas')]
#[IsGranted('ROLE_VPSAS')]
class VpsasController extends AbstractController
{
    #[Route('', name: 'vpsas_dashboard', methods: ['GET'])]
    public function dashboard(DashboardStats $stats): Response
    {
        return $this->render('vpsas/dashboard.html.twig', [
            'stats' => $stats->compile(),
        ]);
    }
}
