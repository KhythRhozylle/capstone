<?php

namespace App\Controller;

use App\Entity\CareFollowUp;
use App\Entity\User;
use App\Enum\CareCaseStatus;
use App\Form\FollowUpType;
use App\Repository\CareCaseRepository;
use App\Service\AppNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/care')]
#[IsGranted('ROLE_GUIDANCE')]
class CareController extends AbstractController
{
    #[Route('', name: 'care_dashboard', methods: ['GET'])]
    public function dashboard(CareCaseRepository $cases, #[CurrentUser] User $user): Response
    {
        return $this->render('care/dashboard.html.twig', [
            'cases' => $cases->findForDesk($user),
        ]);
    }

    #[Route('/cases', name: 'care_cases', methods: ['GET'])]
    public function cases(CareCaseRepository $cases, #[CurrentUser] User $user): Response
    {
        return $this->render('care/cases/index.html.twig', [
            'cases' => $cases->findForDesk($user),
        ]);
    }

    #[Route('/cases/{id}', name: 'care_case_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function showCase(
        int $id,
        Request $request,
        CareCaseRepository $cases,
        EntityManagerInterface $entityManager,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $case = $cases->find($id);
        if (!$case) {
            throw $this->createNotFoundException();
        }

        $followUp = new CareFollowUp();
        $followUp->setDueAt(new \DateTimeImmutable('+7 days'));
        $followForm = $this->createForm(FollowUpType::class, $followUp);
        $followForm->handleRequest($request);

        if ($followForm->isSubmitted() && $followForm->isValid()) {
            $case->addFollowUp($followUp);
            $case->setStatus(CareCaseStatus::FollowUp);
            $entityManager->flush();
            $this->addFlash('success', 'Follow-up scheduled.');

            return $this->redirectToRoute('care_case_show', ['id' => $id]);
        }

        return $this->render('care/cases/show.html.twig', [
            'case' => $case,
            'followForm' => $followForm,
        ]);
    }

    #[Route('/cases/{id}/status', name: 'care_case_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function caseStatus(
        int $id,
        Request $request,
        CareCaseRepository $cases,
        EntityManagerInterface $entityManager,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $case = $cases->find($id);
        if (!$case) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('care-status-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = CareCaseStatus::tryFrom((string) $request->request->get('status'));
        if (!$status) {
            $this->addFlash('danger', 'Invalid status.');

            return $this->redirectToRoute('care_case_show', ['id' => $id]);
        }

        $case->setStatus($status);
        if (!$case->getCounselor()) {
            $case->setCounselor($user);
        }
        $summary = trim((string) $request->request->get('studentVisibleSummary'));
        if ($summary !== '') {
            $case->setStudentVisibleSummary($summary);
        }
        $clinical = trim((string) $request->request->get('clinicalNotes'));
        if ($clinical !== '') {
            $case->setClinicalNotes($clinical);
        }
        $entityManager->flush();

        $notifier->notify(
            $case->getStudent(),
            'CARE case update',
            $case->getReferenceCode().' — '.$status->label(),
            $this->generateUrl('portal_care_show', ['id' => $id])
        );
        $this->addFlash('success', 'CARE case updated.');

        return $this->redirectToRoute('care_case_show', ['id' => $id]);
    }

    #[Route('/follow-ups/{id}/complete', name: 'care_follow_up_complete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function completeFollowUp(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $followUp = $entityManager->find(CareFollowUp::class, $id);
        if (!$followUp) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('follow-up-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $followUp->setCompletedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'Follow-up marked complete.');

        return $this->redirectToRoute('care_case_show', ['id' => $followUp->getCareCase()->getId()]);
    }
}
