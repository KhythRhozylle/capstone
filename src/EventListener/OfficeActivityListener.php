<?php

namespace App\EventListener;

use App\Entity\User;
use App\Enum\OfficeProfile;
use App\Service\OfficeProfileContext;
use App\Service\StaffWorkspace;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class OfficeActivityListener
{
    public function __construct(private readonly Security $security, private readonly OfficeProfileContext $profiles, private readonly EntityManagerInterface $manager, private readonly StaffWorkspace $workspace)
    {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLogin(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof User && $this->profiles->supports($user)) {
            $this->profiles->select($user, OfficeProfile::Cesar);
        }
    }

    #[AsEventListener(event: LogoutEvent::class, priority: 128)]
    public function onLogout(LogoutEvent $event): void
    {
        $user = $event->getToken()?->getUser();
        if ($user instanceof User && $this->workspace->supports($user)) {
            $event->getRequest()->attributes->set('_office_activity_actor', $this->actor($user));
        }
    }

    #[AsEventListener(event: ResponseEvent::class, priority: -100)]
    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route', '');
        if ('' === $route || str_starts_with($route, '_') || in_array($route, ['notifications_count', 'app_session_continue'], true) || $request->attributes->get('_browser_session_check')) {
            return;
        }
        $actor = $request->attributes->get('_office_activity_actor');
        if (null === $actor) {
            $user = $this->security->getUser();
            if (!$user instanceof User || !$this->workspace->supports($user)) {
                return;
            }
            $actor = $this->actor($user);
        }
        [$accountId, $profileKey, $actorName] = $actor;
        // Write only metadata; never flush pending ORM changes after a failed action.
        $this->manager->getConnection()->insert('office_activity', [
            'account_id' => $accountId, 'profile_key' => $profileKey, 'actor_name' => $actorName,
            'method' => $request->getMethod(), 'path' => substr($request->getPathInfo(), 0, 2048),
            'route' => $route, 'status_code' => $event->getResponse()->getStatusCode(),
            'occurred_at' => new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ], ['occurred_at' => Types::DATETIME_IMMUTABLE]);
    }

    private function actor(User $user): array
    {
        if ($this->profiles->supports($user)) {
            $profile = $this->profiles->current($user);

            return [$user->getId(), $profile->value, $profile->label()];
        }

        return [$user->getId(), 'account', $user->getFullName()];
    }

}
