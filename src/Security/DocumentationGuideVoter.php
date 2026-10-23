<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DocumentationGuideVoter extends Voter
{
    public const VIEW = 'DOCUMENTATION_VIEW';
    public const MANAGE = 'DOCUMENTATION_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::MANAGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $manager = match (strtolower($user->getEmail())) {
            'vpsas@norsu.edu.ph' => in_array('ROLE_VPSAS', $user->getRoles(), true),
            'secretary@norsu.edu.ph' => in_array('ROLE_VPSAS_SECRETARIAT', $user->getRoles(), true),
            default => false,
        };

        return $manager || (self::VIEW === $attribute && (in_array('ROLE_STUDENT', $user->getRoles(), true) || $user->isStaff()));
    }
}
