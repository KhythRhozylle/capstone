<?php

namespace App\Validator;

use App\Entity\Appointment;
use App\Service\AppointmentAvailability;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class AvailableAppointmentSlotValidator extends ConstraintValidator
{
    public function __construct(private readonly AppointmentAvailability $availability)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableAppointmentSlot) {
            throw new UnexpectedTypeException($constraint, AvailableAppointmentSlot::class);
        }
        if (!$value instanceof Appointment) {
            return;
        }

        // The form may be submitted before every required field is set; those errors are reported elsewhere.
        try {
            $unit = $value->getUnit();
            $campus = $value->getCampus();
            $student = $value->getStudent();
            $when = $value->getScheduledAt();
        } catch (\Error) {
            return;
        }

        $problem = $this->availability->problemWith($unit, $campus, $when, $value, $student);
        if (null !== $problem) {
            $this->context->buildViolation($problem)->atPath('scheduledAt')->addViolation();
        }
    }
}
