<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\DocumentRequest;
use App\Entity\Feedback;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\AppointmentStatus;
use App\Enum\FeedbackKind;
use App\Enum\RequestStatus;
use App\Form\FeedbackType;
use App\Repository\FeedbackRepository;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/** Decides whether a finished record can be rated and prepares the rating form for its detail page. */
class FeedbackPanel
{
    public function __construct(
        private readonly FormFactoryInterface $forms,
        private readonly UrlGeneratorInterface $urls,
        private readonly FeedbackRepository $feedback,
    ) {
    }

    public static function isFinished(Appointment|ServiceRequest|DocumentRequest $subject): bool
    {
        return match (true) {
            $subject instanceof Appointment => AppointmentStatus::Completed === $subject->getStatus(),
            $subject instanceof ServiceRequest => \in_array($subject->getStatus(), [RequestStatus::Resolved, RequestStatus::Closed], true),
            $subject instanceof DocumentRequest => RequestStatus::Released === $subject->getStatus(),
        };
    }

    public static function kindOf(Appointment|ServiceRequest|DocumentRequest $subject): FeedbackKind
    {
        return match (true) {
            $subject instanceof Appointment => FeedbackKind::Appointment,
            $subject instanceof ServiceRequest => FeedbackKind::Service,
            $subject instanceof DocumentRequest => FeedbackKind::Document,
        };
    }

    /** @return array{kind: FeedbackKind, feedback: ?Feedback, form: ?FormView}|null null when there is nothing to show */
    public function build(Appointment|ServiceRequest|DocumentRequest $subject, ?UserInterface $viewer): ?array
    {
        if (!$viewer instanceof User || $subject->getStudent()->getId() !== $viewer->getId() || !self::isFinished($subject)) {
            return null;
        }

        $kind = self::kindOf($subject);
        $existing = $this->feedback->forSubject($subject);
        $form = $existing ? null : $this->forms->create(FeedbackType::class, Feedback::forSubject($viewer, $subject), [
            'action' => $this->urls->generate('portal_feedback_submit', ['kind' => $kind->value, 'id' => $subject->getId()]),
        ])->createView();

        return ['kind' => $kind, 'feedback' => $existing, 'form' => $form];
    }
}
