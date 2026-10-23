<?php

namespace App\Controller;

use App\Dto\ActivityQuery;
use App\Entity\OfficeActivity;
use App\Entity\User;
use App\Service\StaffWorkspace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class StaffActivityController extends AbstractController
{
    #[Route('/staff/activity', name: 'staff_activity', methods: ['GET'])]
    #[IsGranted('ROLE_STAFF')]
    public function index(#[CurrentUser] User $user, StaffWorkspace $workspace, EntityManagerInterface $manager, #[MapQueryString] ActivityQuery $query = new ActivityQuery()): Response
    {
        if (!$workspace->supports($user)) {
            throw $this->createAccessDeniedException();
        }
        $repository = $manager->getRepository(OfficeActivity::class);

        return $this->render('staff/activity.html.twig', ['activities' => $repository->findBy(['accountId' => $user->getId()], ['id' => 'DESC'], 50, ($query->page - 1) * 50), 'page' => $query->page, 'pages' => max(1, (int) ceil($repository->count(['accountId' => $user->getId()]) / 50)), 'activityRoute' => 'staff_activity']);
    }
}
