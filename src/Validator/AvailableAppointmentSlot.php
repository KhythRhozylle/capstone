<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/** Rejects an appointment whose time is outside the unit's office hours or is already taken. */
#[\Attribute(\Attribute::TARGET_CLASS)]
class AvailableAppointmentSlot extends Constraint
{
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
