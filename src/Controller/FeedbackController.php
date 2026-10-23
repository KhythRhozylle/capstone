<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\DocumentRequest;
use App\Entity\Feedback;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\FeedbackKind;
use App\Form\FeedbackType;
use App\Repository\AppointmentRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\FeedbackRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\FeedbackPanel;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** Satisfaction survey a student fills in once an appointment, service request or document request is finished. */
#[IsGranted('ROLE_STUDENT')]
class FeedbackController extends AbstractController
{
    private const SHOW_ROUTES = [
        'appointment' => 'portal_appointment_show',
        'service' => 'portal_service_show',
        'document' => 'portal_document_show',
    ];

    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly ServiceRequestRepository $services,
        private readonly DocumentRequestRepository $documents,
        private readonly FeedbackRepository $feedback,
    ) {
    }

    #[Route('/portal/feedback/{kind}/{id}', name: 'portal_feedback_submit', requirements: ['kind' => 'appointment|service|document', 'id' => '\d+'], methods: ['POST'])]
    public function submit(string $kind, int $id, Request $request, EntityManagerInterface $entityManager, #[CurrentUser] User $user): Response
    {
        $subject = $this->subject($kind, $id, $user);
        if (!$subject) {
            throw $this->createNotFoundException();
        }
        $back = $this->redirectToRoute(self::SHOW_ROUTES[$kind], ['id' => $id]);

        if (!FeedbackPanel::isFinished($subject)) {
            $this->addFlash('danger', 'You can rate this once it is finished.');

            return $back;
        }
        if ($this->feedback->forSubject($subject)) {
            $this->addFlash('info', 'You have already rated this. Thank you!');

            return $back;
        }

        $entry = Feedback::forSubject($user, $subject);
        $form = $this->createForm(FeedbackType::class, $entry);
        $form->handleRequest($request);
        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', (string) ($form->getErrors(true)->current()?->getMessage() ?? 'Please choose a rating from 1 to 5.'));

            return $back;
        }

        $entityManager->persist($entry);
        $entityManager->flush();
        $this->addFlash('success', 'Thank you for your feedback!');

        return $back;
    }

    private function subject(string $kind, int $id, User $user): Appointment|ServiceRequest|DocumentRequest|null
    {
        $subject = match (FeedbackKind::tryFrom($kind)) {
            FeedbackKind::Appointment => $this->appointments->find($id),
            FeedbackKind::Service => $this->services->find($id),
            FeedbackKind::Document => $this->documents->find($id),
            null => null,
        };

        return $subject && $subject->getStudent()->getId() === $user->getId() ? $subject : null;
    }
}
