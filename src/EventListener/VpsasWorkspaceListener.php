<?php

namespace App\EventListener;

use App\Entity\User;
use App\Service\StaffWorkspace;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[AsEventListener(event: ControllerEvent::class)]
class VpsasWorkspaceListener
{
    public function __construct(private readonly Security $security, private readonly StaffWorkspace $workspace)
    {
    }

    public function __invoke(ControllerEvent $event): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->workspace->supports($user)) {
            return;
        }
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (str_starts_with($route, 'care_') && !in_array('ROLE_VPSAS', $user->getRoles(), true) && !$this->workspace->hasCareDesk($user)) {
            throw new AccessDeniedException('The counseling desk is not assigned to this account.');
        }
        if (str_starts_with($route, 'staff_service') || 'staff_queue' === $route) {
            throw new AccessDeniedException('This function is not part of this office workspace.');
        }
    }
}
