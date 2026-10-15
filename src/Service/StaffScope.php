<?php

namespace App\Service;

use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;

readonly class StaffScope
{
    public function __construct(
        public ?Campus $campus,
        public ?SasUnit $unit,
        public bool $unrestricted,
    ) {
    }

    public static function for(User $user): self
    {
        $roles = $user->getRoles();
        $unrestricted = \in_array('ROLE_VPSAS', $roles, true)
            || \in_array('ROLE_VPSAS_SECRETARIAT', $roles, true)
            || \in_array('ROLE_DIRECTOR', $roles, true);

        return new self(
            $unrestricted ? null : $user->getCampus(),
            $unrestricted ? null : $user->getStaffUnit(),
            $unrestricted,
        );
    }
}
