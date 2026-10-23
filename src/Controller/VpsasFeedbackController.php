<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\FeedbackRepository;
use App\Service\StaffWorkspace;
use App\Service\StaffScope;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class VpsasFeedbackController extends AbstractController
{
    #[Route('/vpsas/feedback', name: 'vpsas_feedback', methods: ['GET'])]
    #[Route('/staff/feedback', name: 'staff_feedback', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(#[CurrentUser] User $user, StaffWorkspace $workspace, FeedbackRepository $feedback): Response
    {
        if (!$workspace->supports($user)) {
            throw $this->createAccessDeniedException();
        }

        $units = $workspace->visibleUnits($user);
        $unitIds = null === $units ? null : array_map(static fn ($unit) => $unit->getId(), $units);

        return $this->render('vpsas/feedback.html.twig', ['feedbacks' => $feedback->forUnits($unitIds, StaffScope::for($user)->campus), 'summary' => $feedback->overall($unitIds, StaffScope::for($user)->campus)]);
    }
}
