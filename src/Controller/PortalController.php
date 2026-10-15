<?php

namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\CareCase;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\ConcernType as ConcernKind;
use App\Form\AppointmentType;
use App\Form\CareRequestType;
use App\Form\ConcernType;
use App\Form\DocumentRequestType;
use App\Form\ProfileType;
use App\Form\ServiceRequestType;
use App\Repository\AnnouncementRepository;
use App\Repository\AppointmentRepository;
use App\Repository\CareCaseRepository;
use App\Repository\ConcernRepository;
use App\Repository\DocumentRequestRepository;
use App\Repository\NotificationRepository;
use App\Repository\QueueTicketRepository;
use App\Repository\SasUnitRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\AppNotifier;
use App\Service\CalendarIcs;
use App\Service\QueueService;
use App\Service\ReferenceCodeGenerator;
use App\Service\TrackingService;
use App\Service\WorkspaceActivity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/portal')]
#[IsGranted('ROLE_STUDENT')]
class PortalController extends AbstractController
{
    #[Route('', name: 'portal_dashboard', methods: ['GET'])]
    public function dashboard(
        #[CurrentUser] User $user,
        AnnouncementRepository $announcements,
        AppointmentRepository $appointments,
        ConcernRepository $concerns,
        ServiceRequestRepository $services,
        DocumentRequestRepository $documents,
        CareCaseRepository $careCases,
        NotificationRepository $notifications,
        WorkspaceActivity $activity,
    ): Response {
        $appointmentRows = $appointments->findForStudent($user);
        $concernRows = $concerns->findForStudent($user);
        $serviceRows = $services->findForStudent($user);
        $documentRows = $documents->findForStudent($user);

        return $this->render('portal/dashboard.html.twig', [
            'announcements' => $announcements->visibleFor($user->getCampus(), 5),
            'appointments' => \array_slice($appointmentRows, 0, 5),
            'concerns' => \array_slice($concernRows, 0, 5),
            'services' => \array_slice($serviceRows, 0, 5),
            'documents' => \array_slice($documentRows, 0, 5),
            'activity' => $activity->summarize($appointmentRows, $concernRows, $serviceRows, $documentRows),
            'careCases' => \array_slice($careCases->findForStudent($user), 0, 5),
            'notifications' => $notifications->latestFor($user, 8),
            'unreadCount' => $notifications->unreadCount($user),
        ]);
    }

    #[Route('/profile', name: 'portal_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        $profile = $user->getStudentProfile();
        $form = $this->createForm(ProfileType::class, $user, ['profile' => $profile]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $profile instanceof \App\Entity\StudentProfile) {
            $profile->setContactNumber((string) $form->get('contactNumber')->getData());
            $profile->setProgram((string) $form->get('program')->getData());
            $profile->setCollege((string) $form->get('college')->getData());
            $profile->setYearLevel((string) $form->get('yearLevel')->getData());
            $entityManager->flush();
            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('portal_profile');
        }

        return $this->render('portal/profile.html.twig', [
            'form' => $form,
            'profile' => $profile,
        ]);
    }

    #[Route('/notifications', name: 'portal_notifications', methods: ['GET'])]
    public function notifications(
        NotificationRepository $notifications,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/notifications.html.twig', [
            'notifications' => $notifications->latestFor($user, 50),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'portal_notification_read', methods: ['POST'])]
    public function readNotification(
        int $id,
        Request $request,
        NotificationRepository $notifications,
        EntityManagerInterface $entityManager,
        #[CurrentUser] User $user,
    ): Response {
        if (!$this->isCsrfTokenValid('notification-read-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notification = $notifications->find($id);
        if (!$notification || $notification->getRecipient()->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException();
        }
        $notification->markRead();
        $entityManager->flush();

        return $this->redirectToRoute('portal_notifications');
    }

    #[Route('/notifications/read-all', name: 'portal_notifications_read_all', methods: ['POST'])]
    public function readAllNotifications(
        Request $request,
        NotificationRepository $notifications,
        #[CurrentUser] User $user,
    ): Response {
        if (!$this->isCsrfTokenValid('notifications-read-all', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        $notifications->markAllRead($user);

        return $this->redirectToRoute('portal_notifications');
    }

    #[Route('/announcements', name: 'portal_announcements', methods: ['GET'])]
    public function announcementList(
        AnnouncementRepository $announcements,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/announcements.html.twig', [
            'announcements' => $announcements->visibleFor($user->getCampus(), 50),
        ]);
    }

    #[Route('/appointments', name: 'portal_appointments', methods: ['GET'])]
    public function appointments(
        AppointmentRepository $appointments,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/appointments/index.html.twig', [
            'appointments' => $appointments->findForStudent($user),
        ]);
    }

    #[Route('/appointments/new', name: 'portal_appointment_new', methods: ['GET', 'POST'])]
    public function newAppointment(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceCodeGenerator $codes,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            $this->addFlash('danger', 'Your account has no campus assigned. Contact SAS staff.');

            return $this->redirectToRoute('portal_dashboard');
        }

        $appointment = new Appointment();
        $appointment->setStudent($user);
        $appointment->setCampus($campus);
        $form = $this->createForm(AppointmentType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($appointment);
            $entityManager->flush();
            $appointment->setReferenceCode($codes->generate('APT', $appointment));
            $tracking->record($appointment, null, $appointment->getStatus()->label(), $user, 'Appointment submitted.');
            $entityManager->flush();

            $notifier->notify($user, 'Appointment submitted', 'Reference '.$appointment->getReferenceCode().' is pending staff approval.', $this->generateUrl('portal_appointment_show', ['id' => $appointment->getId()]));
            $this->addFlash('success', 'Appointment request submitted. Reference: '.$appointment->getReferenceCode());

            return $this->redirectToRoute('portal_appointment_show', ['id' => $appointment->getId()]);
        }

        return $this->render('portal/appointments/form.html.twig', [
            'form' => $form,
            'title' => 'Book an appointment',
        ]);
    }

    #[Route('/appointments/{id}', name: 'portal_appointment_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showAppointment(
        int $id,
        AppointmentRepository $appointments,
        TrackingService $tracking,
        #[CurrentUser] User $user,
    ): Response {
        $appointment = $this->loadStudentAppointment($appointments, $id, $user);

        return $this->render('portal/appointments/show.html.twig', [
            'appointment' => $appointment,
            'history' => $tracking->history($appointment),
        ]);
    }

    #[Route('/appointments/{id}/calendar.ics', name: 'portal_appointment_ics', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function appointmentCalendar(
        int $id,
        AppointmentRepository $appointments,
        CalendarIcs $calendar,
        #[CurrentUser] User $user,
    ): Response {
        return $calendar->appointmentResponse($this->loadStudentAppointment($appointments, $id, $user));
    }

    #[Route('/appointments/{id}/reschedule', name: 'portal_appointment_reschedule', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rescheduleAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointments,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        #[CurrentUser] User $user,
    ): Response {
        $appointment = $this->loadStudentAppointment($appointments, $id, $user);
        if (!$this->isCsrfTokenValid('appointment-reschedule-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$appointment->canStudentChange()) {
            $this->addFlash('danger', 'This appointment can no longer be rescheduled.');

            return $this->redirectToRoute('portal_appointment_show', ['id' => $id]);
        }

        $when = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', (string) $request->request->get('scheduledAt'));
        if (!$when instanceof \DateTimeImmutable) {
            $this->addFlash('danger', 'Please choose a valid date and time.');

            return $this->redirectToRoute('portal_appointment_show', ['id' => $id]);
        }

        $from = $appointment->getStatus()->label();
        $appointment->setScheduledAt($when);
        $appointment->setStatus(AppointmentStatus::Pending);
        $tracking->record($appointment, $from, AppointmentStatus::Pending->label(), $user, 'Student requested a new schedule.');
        $entityManager->flush();
        $this->addFlash('success', 'Reschedule request submitted for staff approval.');

        return $this->redirectToRoute('portal_appointment_show', ['id' => $id]);
    }

    #[Route('/appointments/{id}/cancel', name: 'portal_appointment_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancelAppointment(
        int $id,
        Request $request,
        AppointmentRepository $appointments,
        EntityManagerInterface $entityManager,
        TrackingService $tracking,
        #[CurrentUser] User $user,
    ): Response {
        $appointment = $this->loadStudentAppointment($appointments, $id, $user);
        if (!$this->isCsrfTokenValid('appointment-cancel-'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }
        if (!$appointment->canStudentChange()) {
            $this->addFlash('danger', 'This appointment can no longer be cancelled.');

            return $this->redirectToRoute('portal_appointment_show', ['id' => $id]);
        }

        $from = $appointment->getStatus()->label();
        $appointment->setStatus(AppointmentStatus::Cancelled);
        $tracking->record($appointment, $from, AppointmentStatus::Cancelled->label(), $user, 'Cancelled by student.');
        $entityManager->flush();
        $this->addFlash('success', 'Appointment cancelled.');

        return $this->redirectToRoute('portal_appointment_show', ['id' => $id]);
    }

    #[Route('/queue', name: 'portal_queue', methods: ['GET', 'POST'])]
    public function queue(
        Request $request,
        QueueService $queueService,
        QueueTicketRepository $tickets,
        SasUnitRepository $units,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            $this->addFlash('danger', 'Your account has no campus assigned.');

            return $this->redirectToRoute('portal_dashboard');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('portal-queue', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException();
            }
            $unit = $units->find((int) $request->request->get('unit'));
            if (!$unit || !$unit->handlesAppointments()) {
                $this->addFlash('danger', 'Choose a SAS unit that accepts walk-ins.');

                return $this->redirectToRoute('portal_queue');
            }
            $ticket = $queueService->issue($campus, $unit, $user->getFullName(), $user);
            $this->addFlash('success', 'Walk-in ticket #'.$ticket->getNumber().' issued for '.$unit->getName().'.');

            return $this->redirectToRoute('portal_queue');
        }

        return $this->render('portal/queue.html.twig', [
            'tickets' => $tickets->todayForStudent($user),
            'units' => $units->findAppointmentUnits(),
        ]);
    }

    #[Route('/services', name: 'portal_services', methods: ['GET'])]
    public function services(
        ServiceRequestRepository $services,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/services/index.html.twig', [
            'requests' => $services->findForStudent($user),
        ]);
    }

    #[Route('/services/new', name: 'portal_service_new', methods: ['GET', 'POST'])]
    public function newService(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceCodeGenerator $codes,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $service = new ServiceRequest();
        $service->setStudent($user);
        $service->setCampus($campus);
        $form = $this->createForm(ServiceRequestType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($service);
            $entityManager->flush();
            $service->setReferenceCode($codes->generate('SVC', $service));
            $tracking->record($service, null, $service->getStatus()->label(), $user);
            $entityManager->flush();
            $notifier->notify($user, 'Service request submitted', 'Reference '.$service->getReferenceCode(), $this->generateUrl('portal_service_show', ['id' => $service->getId()]));
            $this->addFlash('success', 'Service request submitted.');

            return $this->redirectToRoute('portal_service_show', ['id' => $service->getId()]);
        }

        return $this->render('portal/services/form.html.twig', ['form' => $form]);
    }

    #[Route('/services/{id}', name: 'portal_service_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showService(
        int $id,
        ServiceRequestRepository $services,
        TrackingService $tracking,
        #[CurrentUser] User $user,
    ): Response {
        $service = $services->find($id);
        if (!$service || $service->getStudent()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('portal/services/show.html.twig', [
            'request' => $service,
            'history' => $tracking->history($service),
        ]);
    }

    #[Route('/concerns', name: 'portal_concerns', methods: ['GET'])]
    public function concerns(
        ConcernRepository $concerns,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/concerns/index.html.twig', [
            'concerns' => $concerns->findForStudent($user),
        ]);
    }

    #[Route('/concerns/new', name: 'portal_concern_new', methods: ['GET', 'POST'])]
    public function newConcern(
        Request $request,
        EntityManagerInterface $entityManager,
        SasUnitRepository $units,
        ReferenceCodeGenerator $codes,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $concern = new Concern();
        $concern->setStudent($user);
        $concern->setCampus($campus);
        $form = $this->createForm(ConcernType::class, $concern);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = $concern->getType() === ConcernKind::Complaint ? 'DISC' : 'OSAS';
            $unit = $units->findOneBy(['code' => $code]);
            if (!$unit) {
                $this->addFlash('danger', 'Unable to route your concern. Contact SAS staff.');

                return $this->redirectToRoute('portal_concerns');
            }
            $concern->setAssignedUnit($unit);
            $entityManager->persist($concern);
            $entityManager->flush();
            $concern->setReferenceCode($codes->generate('GRV', $concern));
            $tracking->record($concern, null, $concern->getStatus()->label(), $user);
            $entityManager->flush();
            $notifier->notify($user, 'Concern submitted', 'Reference '.$concern->getReferenceCode(), $this->generateUrl('portal_concern_show', ['id' => $concern->getId()]));
            $this->addFlash('success', 'Your concern was submitted.');

            return $this->redirectToRoute('portal_concern_show', ['id' => $concern->getId()]);
        }

        return $this->render('portal/concerns/form.html.twig', ['form' => $form]);
    }

    #[Route('/concerns/{id}', name: 'portal_concern_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showConcern(
        int $id,
        ConcernRepository $concerns,
        TrackingService $tracking,
        #[CurrentUser] User $user,
    ): Response {
        $concern = $concerns->find($id);
        if (!$concern || $concern->getStudent()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('portal/concerns/show.html.twig', [
            'concern' => $concern,
            'history' => $tracking->history($concern),
        ]);
    }

    #[Route('/documents', name: 'portal_documents', methods: ['GET'])]
    public function documents(
        DocumentRequestRepository $documents,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/documents/index.html.twig', [
            'documents' => $documents->findForStudent($user),
        ]);
    }

    #[Route('/documents/new', name: 'portal_document_new', methods: ['GET', 'POST'])]
    public function newDocument(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceCodeGenerator $codes,
        TrackingService $tracking,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $document = new DocumentRequest();
        $document->setStudent($user);
        $document->setCampus($campus);
        $form = $this->createForm(DocumentRequestType::class, $document);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($document);
            $entityManager->flush();
            $document->setReferenceCode($codes->generate('DOC', $document));
            $tracking->record($document, null, $document->getStatus()->label(), $user);
            $entityManager->flush();
            $notifier->notify($user, 'Document request submitted', 'Reference '.$document->getReferenceCode(), $this->generateUrl('portal_document_show', ['id' => $document->getId()]));
            $this->addFlash('success', 'Document request submitted.');

            return $this->redirectToRoute('portal_document_show', ['id' => $document->getId()]);
        }

        return $this->render('portal/documents/form.html.twig', ['form' => $form]);
    }

    #[Route('/documents/{id}', name: 'portal_document_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showDocument(
        int $id,
        DocumentRequestRepository $documents,
        TrackingService $tracking,
        UrlGeneratorInterface $urls,
        #[CurrentUser] User $user,
    ): Response {
        $document = $documents->find($id);
        if (!$document || $document->getStudent()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('portal/documents/show.html.twig', [
            'document' => $document,
            'history' => $tracking->history($document),
            'verifyUrl' => $urls->generate('app_verify_document', ['token' => $document->getVerificationToken()], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/care', name: 'portal_care', methods: ['GET'])]
    public function careCases(
        CareCaseRepository $careCases,
        #[CurrentUser] User $user,
    ): Response {
        return $this->render('portal/care/index.html.twig', [
            'cases' => $careCases->findForStudent($user),
        ]);
    }

    #[Route('/care/new', name: 'portal_care_new', methods: ['GET', 'POST'])]
    public function newCareCase(
        Request $request,
        EntityManagerInterface $entityManager,
        ReferenceCodeGenerator $codes,
        AppNotifier $notifier,
        #[CurrentUser] User $user,
    ): Response {
        $campus = $user->getCampus();
        if (!$campus) {
            return $this->redirectToRoute('portal_dashboard');
        }

        $case = new CareCase();
        $case->setStudent($user);
        $case->setCampus($campus);
        $form = $this->createForm(CareRequestType::class, $case);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($case);
            $entityManager->flush();
            $case->setReferenceCode($codes->generate('CARE', $case));
            $entityManager->flush();
            $notifier->notify($user, 'CARE request received', 'Reference '.$case->getReferenceCode().'. A counselor will contact you.', $this->generateUrl('portal_care_show', ['id' => $case->getId()]));
            $this->addFlash('success', 'CARE counseling request submitted.');

            return $this->redirectToRoute('portal_care_show', ['id' => $case->getId()]);
        }

        return $this->render('portal/care/form.html.twig', ['form' => $form]);
    }

    #[Route('/care/{id}', name: 'portal_care_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function showCareCase(
        int $id,
        CareCaseRepository $careCases,
        #[CurrentUser] User $user,
    ): Response {
        $case = $careCases->find($id);
        if (!$case || $case->getStudent()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $this->render('portal/care/show.html.twig', ['case' => $case]);
    }

    private function loadStudentAppointment(AppointmentRepository $appointments, int $id, User $user): Appointment
    {
        $appointment = $appointments->find($id);
        if (!$appointment || $appointment->getStudent()->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }

        return $appointment;
    }
}
