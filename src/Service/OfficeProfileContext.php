<?php

namespace App\Service;

use App\Entity\User;
use App\Enum\OfficeProfile;
use Symfony\Component\HttpFoundation\RequestStack;

class OfficeProfileContext
{
    public function __construct(private readonly RequestStack $requests)
    {
    }

    public function supports(?User $user): bool
    {
        return $user instanceof User && 'vpsas@norsu.edu.ph' === strtolower($user->getEmail())
            && in_array('ROLE_VPSAS', $user->getRoles(), true);
    }

    public function current(User $user): OfficeProfile
    {
        if (!$this->supports($user)) {
            throw new \LogicException('This account does not have office profiles.');
        }
        $request = $this->requests->getMainRequest();
        $key = $request?->hasSession() ? $request->getSession()->get('vpsas_actor_'.$user->getId(), 'cesar') : 'cesar';

        return is_string($key) ? (OfficeProfile::tryFrom($key) ?? OfficeProfile::Cesar) : OfficeProfile::Cesar;
    }

    public function select(User $user, OfficeProfile $profile): void
    {
        if (!$this->supports($user)) {
            throw new \LogicException('This account does not have office profiles.');
        }
        $this->requests->getSession()->set('vpsas_actor_'.$user->getId(), $profile->value);
    }
}
