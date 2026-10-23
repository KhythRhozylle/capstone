<?php

namespace App\Twig;

use App\Entity\Appointment;
use App\Entity\DocumentRequest;
use App\Entity\ServiceRequest;
use App\Service\FeedbackPanel;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

class FeedbackExtension
{
    public function __construct(private readonly FeedbackPanel $panel, private readonly Security $security)
    {
    }

    /** @return array<string, mixed>|null */
    #[AsTwigFunction('feedback_panel')]
    public function feedbackPanel(Appointment|ServiceRequest|DocumentRequest $subject): ?array
    {
        return $this->panel->build($subject, $this->security->getUser());
    }
}
