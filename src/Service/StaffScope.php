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
        public ?string $directorateCode = null,
    ) {
    }

    public static function for(User $user): self
    {
        $roles = $user->getRoles();
        if ($code = StaffWorkspace::directorateCode($user)) {
            return new self(null, null, false, $code);
        }
        if (in_array('ROLE_UNIT_HEAD', $roles, true) && null === $user->getStaffUnit()) {
            return new self($user->getCampus(), null, false, '__UNASSIGNED__');
        }
        $unrestricted = \in_array('ROLE_VPSAS', $roles, true)
            || \in_array('ROLE_VPSAS_SECRETARIAT', $roles, true)
            || \in_array('ROLE_DIRECTOR', $roles, true);

        return new self(
            $unrestricted ? null : $user->getCampus(),
            $unrestricted ? null : $user->getStaffUnit(),
            $unrestricted,
        );
    }

    public function allows(Campus $campus, SasUnit $unit): bool
    {
        if ($this->unrestricted) {
            return true;
        }
        if ($this->campus && $this->campus->getId() !== $campus->getId()) {
            return false;
        }
        if ($this->directorateCode) {
            return $unit->getDirectorate()->getCode() === $this->directorateCode && 'VPSAS' !== $unit->getCode();
        }

        return null === $this->unit || $this->unit->getId() === $unit->getId();
    }
}
