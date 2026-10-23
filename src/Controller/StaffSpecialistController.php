<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\StaffWorkspace;
use App\Repository\SasUnitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_STAFF')]
class StaffSpecialistController extends AbstractController
{
    #[Route('/staff/specialist', name: 'staff_specialist_desk', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, StaffWorkspace $workspace, SasUnitRepository $unitRepository): Response
    {
        if (!$workspace->supports($user)) {
            throw $this->createAccessDeniedException();
        }

        $units = $workspace->visibleUnits($user) ?? $unitRepository->findBy([], ['name' => 'ASC']);
        $purposes = [];
        foreach ($units as $unit) {
            $purposes[$unit->getId()] = $workspace->purpose($unit);
        }

        return $this->render('staff/specialist.html.twig', ['units' => $units, 'purposes' => $purposes, 'office' => $workspace->personalUnit($user)]);
    }
}
