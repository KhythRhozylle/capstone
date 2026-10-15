<?php

namespace App\Controller;

use App\Entity\Announcement;
use App\Entity\CareCase;
use App\Entity\User;
use App\Enum\AnnouncementScope;
use App\Enum\AppointmentStatus;
use App\Enum\QueueStatus;
use App\Enum\RequestStatus;
use App\Form\AnnouncementType;
use App\Form\ReferralType;
use App\Repository\AnnouncementRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ConcernRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\QueueTicketRepository;
use App\Repository\ServiceRequestRepository;
use App\Repository\UserRepository;
use App\Service\AppNotifier;
use App\Service\CalendarIcs;
use App\Service\QueueService;
use App\Service\ReferenceCodeGenerator;
use App\Service\StaffScope;
use App\Service\TrackingService;
use App\Service\WorkspaceActivity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/staff')]
#[IsGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    #[Route('', name: 'staff_dashboard', methods: ['GET'])]
    public function dashboard(
        #[CurrentUser] User $user,
        AppointmentRepository $appointments,
        ConcernRepository $concerns,
        ServiceRequestRepository $services,
        DocumentRequestRepository $documents,
        WorkspaceActivity $activity,
    ): Response {
        $scope = StaffScope::for($user);
        $appointmentRows = $appointments->findForStaff($scope->unit, $scope->campus);
        $concernRows = $concerns->findForStaff($scope->unit, $scope->campus);
        $serviceRows = $services->findForStaff($scope->unit, $scope->campus);
        $documentRows = $documents->findForStaff($scope->unit, $scope->campus);

        return $this->render('staff/dashboard.html.twig', [
            'appointments' => \array_slice($appointmentRows, 0, 8),
            'concerns' => \array_slice($concernRows, 0, 8),
            'services' => \array_slice($serviceRows, 0, 8),
            'documents' => \array_slice($documentRows, 0, 8),
            'activity' => $activity->summarize($appointmentRows, $concernRows, $serviceRows, $documentRows),
            'scope' => $scope,
        ]);
    }

    #[Route('/appointments', name: 'staff_appointments', methods: ['GET'])]
    public function appointments(AppointmentRepository $appointments, #[CurrentUser] User $user): Response
    {
        $scope = StaffScope::for($user);

        return $this->render('staff/appointments/index.html.twig', [
            'appointments' => $appointments->findForStaff($scope->unit, $scope->campus),
        ]);
    }

    #[Route('/appointments/calendar', name: 'staff_appointment_calendar', methods: ['GET'])]
    public function appointmentCalendar(AppointmentRepository $appointments, #[CurrentUser] User $user): Response
    {
        $scope = StaffScope::for($user);
        $start = new \DateTimeImmutable('monday this week 00:00');
        $end = $start->modify('+7 days');
        $rows = $appointments->scheduledBetween($scope->unit, $scope->campus, $start, $end);
        $days = [];
        for ($i = 0; $i < 7; ++$i) {
            $day = $start->modify('+'.$i.' days');
            $days[$day->format('Y-m-d')] = [
                'label' => $day->format('D, M j'),
                'items' => [],
            ];
        }
        foreach ($rows as $appointment) {
            $key = $appointment->getScheduledAt()->format('Y-m-d');
            if (isset($days[$key])) {
                $days[$key]['items'][] = $appointment;
            }
        }

        return $this->render('staff/appointments/calendar.html.twig', [
            'days' => $days,
            'start' => $start,
            'end' => $end,
        ]);
    }

    #[Route('/appointments/{id}', name: 'staff_appointment_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showAppointment(int $id, AppointmentRepository $appointments, TrackingService $tracking, #[CurrentUser] User $user): Response
    {
        $appointment = $this->loadStaffAppointment($appointments, $id, $user);

        return $this->render('staff/appointments/show.html.twig', [
            'appointment' => $appointment,
            'history' => $tracking->history($appointment),
        ]);
    }

    #[Route('/appointments/{id}/status', name: 'staff_appointment_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function appointmentStatus(
        int $id,
        Request $request,
        AppointmentRepository $appointments,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $appointment = $this->loadStaffAppointment($appointments, $id, $user);
        if (!$this->isCsrfTokenValid('appointment-status-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = AppointmentStatus::tryFrom((string) $request->request->get('status'));
        if (!$status) {
            $this->addFlash('danger', 'Invalid status.');

            return $this->redirectToRoute('staff_appointment_show', ['id' => $id]);
        }

        $from = $appointment->getStatus()->label();
        $appointment->setStatus($status);
        $tracking->record($appointment, $from, $status->label(), $user, (string) $request->request->get('note'));
        $entityManager->flush();

        $notifier->notify(
            $appointment->getStudent(),
            'Appointment update',
            $appointment->getReferenceCode().' is now '.$status->label(),
            $this->generateUrl('portal_appointment_show', ['id' => $id])
        );
        $this->addFlash('success', 'Appointment status updated.');

        return $this->redirectToRoute('staff_appointment_show', ['id' => $id]);
    }

    #[Route('/concerns', name: 'staff_concerns', methods: ['GET'])]
    public function concerns(ConcernRepository $concerns, #[CurrentUser] User $user): Response
    {
        $scope = StaffScope::for($user);

        return $this->render('staff/concerns/index.html.twig', [
            'concerns' => $concerns->findForStaff($scope->unit, $scope->campus),
        ]);
    }

    #[Route('/concerns/{id}', name: 'staff_concern_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function showConcern(
        int $id,
        Request $request,
        ConcernRepository $concerns,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $concern = $this->loadStaffConcern($concerns, $id, $user);
        $referForm = $this->createForm(ReferralType::class);
        $referForm->handleRequest($request);

        if ($referForm->isSubmitted() && $referForm->isValid()) {
            $unit = $referForm->get('unit')->getData();
            $from = $concern->getStatus()->label();
            $concern->setReferredToUnit($unit);
            $concern->setStatus(RequestStatus::Referred);
            $note = (string) $referForm->get('note')->getData();
            $tracking->record($concern, $from, RequestStatus::Referred->label(), $user, $note !== '' ? $note : 'Referred to '.$unit->getName());
            $entityManager->flush();
            $notifier->notify($concern->getStudent(), 'Concern referred', $concern->getReferenceCode().' was referred to '.$unit->getName(), $this->generateUrl('portal_concern_show', ['id' => $id]));
            $this->addFlash('success', 'Concern referred.');

            return $this->redirectToRoute('staff_concern_show', ['id' => $id]);
        }

        return $this->render('staff/concerns/show.html.twig', [
            'concern' => $concern,
            'history' => $tracking->history($concern),
            'referForm' => $referForm,
        ]);
    }

    #[Route('/concerns/{id}/status', name: 'staff_concern_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function concernStatus(
        int $id,
        Request $request,
        ConcernRepository $concerns,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $concern = $this->loadStaffConcern($concerns, $id, $user);
        if (!$this->isCsrfTokenValid('concern-status-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = RequestStatus::tryFrom((string) $request->request->get('status'));
        if (!$status) {
            $this->addFlash('danger', 'Invalid status.');

            return $this->redirectToRoute('staff_concern_show', ['id' => $id]);
        }

        $from = $concern->getStatus()->label();
        $concern->setStatus($status);
        if (\in_array($status, [RequestStatus::Resolved, RequestStatus::Closed], true)) {
            $concern->setResolvedAt(new \DateTimeImmutable());
            $concern->setResolutionNotes((string) $request->request->get('note'));
        }
        $tracking->record($concern, $from, $status->label(), $user, (string) $request->request->get('note'));
        $entityManager->flush();
        $notifier->notify($concern->getStudent(), 'Concern update', $concern->getReferenceCode().' — '.$status->label(), $this->generateUrl('portal_concern_show', ['id' => $id]));
        $this->addFlash('success', 'Concern status updated.');

        return $this->redirectToRoute('staff_concern_show', ['id' => $id]);
    }

    #[Route('/services', name: 'staff_services', methods: ['GET'])]
    public function services(ServiceRequestRepository $services, #[CurrentUser] User $user): Response
    {
        $scope = StaffScope::for($user);

        return $this->render('staff/services/index.html.twig', [
            'requests' => $services->findForStaff($scope->unit, $scope->campus),
        ]);
    }

    #[Route('/services/{id}', name: 'staff_service_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showService(int $id, ServiceRequestRepository $services, TrackingService $tracking, #[CurrentUser] User $user): Response
    {
        $service = $this->loadStaffService($services, $id, $user);

        return $this->render('staff/services/show.html.twig', [
            'request' => $service,
            'history' => $tracking->history($service),
        ]);
    }

    #[Route('/services/{id}/status', name: 'staff_service_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function serviceStatus(
        int $id,
        Request $request,
        ServiceRequestRepository $services,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $service = $this->loadStaffService($services, $id, $user);
        if (!$this->isCsrfTokenValid('service-status-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = RequestStatus::tryFrom((string) $request->request->get('status'));
        if (!$status) {
            $this->addFlash('danger', 'Invalid status.');

            return $this->redirectToRoute('staff_service_show', ['id' => $id]);
        }

        $from = $service->getStatus()->label();
        $service->setStatus($status);
        $tracking->record($service, $from, $status->label(), $user, (string) $request->request->get('note'));
        $entityManager->flush();
        $notifier->notify($service->getStudent(), 'Service request update', $service->getReferenceCode().' — '.$status->label(), $this->generateUrl('portal_service_show', ['id' => $id]));
        $this->addFlash('success', 'Request status updated.');

        return $this->redirectToRoute('staff_service_show', ['id' => $id]);
    }

    #[Route('/documents', name: 'staff_documents', methods: ['GET'])]
    public function documents(DocumentRequestRepository $documents, #[CurrentUser] User $user): Response
    {
        $scope = StaffScope::for($user);

        return $this->render('staff/documents/index.html.twig', [
            'documents' => $documents->findForStaff($scope->unit, $scope->campus),
        ]);
    }

    #[Route('/documents/{id}', name: 'staff_document_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showDocument(int $id, DocumentRequestRepository $documents, TrackingService $tracking, #[CurrentUser] User $user): Response
    {
        $document = $this->loadStaffDocument($documents, $id, $user);

        return $this->render('staff/documents/show.html.twig', [
            'document' => $document,
            'history' => $tracking->history($document),
        ]);
    }

    #[Route('/documents/{id}/status', name: 'staff_document_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function documentStatus(
        int $id,
        Request $request,
        DocumentRequestRepository $documents,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $document = $this->loadStaffDocument($documents, $id, $user);
        if (!$this->isCsrfTokenValid('document-status-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $status = RequestStatus::tryFrom((string) $request->request->get('status'));
        if (!$status) {
            $this->addFlash('danger', 'Invalid status.');

            return $this->redirectToRoute('staff_document_show', ['id' => $id]);
        }

        $from = $document->getStatus()->label();
        $document->setStatus($status);
        if ($status === RequestStatus::Released) {
            $document->setReleasedAt(new \DateTimeImmutable());
        }
        $tracking->record($document, $from, $status->label(), $user, (string) $request->request->get('note'));
        $entityManager->flush();
        $notifier->notify($document->getStudent(), 'Document request update', $document->getReferenceCode().' — '.$status->label(), $this->generateUrl('portal_document_show', ['id' => $id]));
        $this->addFlash('success', 'Document status updated.');

        return $this->redirectToRoute('staff_document_show', ['id' => $id]);
    }

    #[Route('/queue', name: 'staff_queue', methods: ['GET', 'POST'])]
    public function queue(
        Request $request,
        QueueService $queueService,
        QueueTicketRepository $tickets,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        $unit = $user->getStaffUnit();
        if (!$campus || !$unit) {
            $this->addFlash('warning', 'Queue management requires a campus and SAS unit on your staff profile.');

            return $this->redirectToRoute('staff_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('queue-call', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $ticket = $queueService->callNext($campus, $unit);
            if ($ticket) {
                $this->addFlash('success', 'Called ticket #'.$ticket->getNumber().' — '.$ticket->getDisplayName());
            } else {
                $this->addFlash('info', 'No tickets waiting in the queue.');
            }

            return $this->redirectToRoute('staff_queue');
        }

        return $this->render('staff/queue.html.twig', [
            'tickets' => $tickets->todayFor($campus, $unit),
            'campus' => $campus,
            'unit' => $unit,
        ]);
    }

    #[Route('/announcements', name: 'staff_announcements', methods: ['GET'])]
    public function announcements(AnnouncementRepository $announcements): Response
    {
        return $this->render('staff/announcements/index.html.twig', [
            'announcements' => $announcements->findBy([], ['publishedAt' => 'DESC'], 50),
        ]);
    }

    #[Route('/announcements/new', name: 'staff_announcement_new', methods: ['GET', 'POST'])]
    public function newAnnouncement(
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $announcement = new Announcement();
        $announcement->setPublishedBy($user);
        $form = $this->createForm(AnnouncementType::class, $announcement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($announcement->getScope() === AnnouncementScope::University) {
                $announcement->setCampus(null);
            }
            $entityManager->persist($announcement);
            $entityManager->flush();

            $recipients = array_filter(
                $users->findByRole('ROLE_STUDENT'),
                function (User $student) use ($announcement): bool {
                    if ($announcement->getScope() !== AnnouncementScope::Campus || !$announcement->getCampus()) {
                        return true;
                    }

                    return $student->getCampus()?->getId() === $announcement->getCampus()->getId();
                }
            );
            $notifier->notifyMany(
                $recipients,
                $announcement->getTitle(),
                $announcement->getBody(),
                $this->generateUrl('portal_announcements')
            );

            $this->addFlash('success', 'Announcement published.');

            return $this->redirectToRoute('staff_announcements');
        }

        return $this->render('staff/announcements/form.html.twig', ['form' => $form]);
    }

    private function loadStaffAppointment(AppointmentRepository $appointments, int $id, User $user): \App\Entity\Appointment
    {
        $appointment = $appointments->find($id);
        if (!$appointment) {
            throw $this->createNotFoundException();
        }
        $this->assertStaffEntityAccess($user, $appointment->getCampus(), $appointment->getUnit());

        return $appointment;
    }

    private function loadStaffConcern(ConcernRepository $concerns, int $id, User $user): \App\Entity\Concern
    {
        $concern = $concerns->find($id);
        if (!$concern) {
            throw $this->createNotFoundException();
        }
        $unit = $concern->getAssignedUnit();
        if ($concern->getReferredToUnit()) {
            $unit = $concern->getReferredToUnit();
        }
        $this->assertStaffEntityAccess($user, $concern->getCampus(), $unit);

        return $concern;
    }

    private function loadStaffService(ServiceRequestRepository $services, int $id, User $user): \App\Entity\ServiceRequest
    {
        $service = $services->find($id);
        if (!$service) {
            throw $this->createNotFoundException();
        }
        $this->assertStaffEntityAccess($user, $service->getCampus(), $service->getUnit());

        return $service;
    }

    private function loadStaffDocument(DocumentRequestRepository $documents, int $id, User $user): \App\Entity\DocumentRequest
    {
        $document = $documents->find($id);
        if (!$document) {
            throw $this->createNotFoundException();
        }
        $this->assertStaffEntityAccess($user, $document->getCampus(), $document->getUnit());

        return $document;
    }

    private function assertStaffEntityAccess(User $user, \App\Entity\Campus $campus, \App\Entity\SasUnit $unit): void
    {
        $scope = StaffScope::for($user);
        if ($scope->unrestricted) {
            return;
        }
        if ($scope->campus && $scope->campus->getId() !== $campus->getId()) {
            throw $this->createAccessDeniedException();
        }
        if ($scope->unit && $scope->unit->getId() !== $unit->getId()) {
            throw $this->createAccessDeniedException();
        }
    }
}
