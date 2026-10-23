<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Repository\OfficeHourRepository;
use App\Repository\OfficeUnavailableRepository;
use Symfony\Component\Clock\ClockInterface;

/**
 * Works out which appointment slots of a SAS unit can be booked.
 *
 * Slots start at each opening time of the unit and last SLOT_MINUTES. A unit that has no office hours
 * recorded yet falls back to Monday to Friday, 8:00-12:00 and 13:00-19:00, so booking keeps working.
 * Each unit can serve one appointment per slot on a campus.
 */
class AppointmentAvailability
{
    public const SLOT_MINUTES = 60;
    public const TIMEZONE = 'Asia/Manila';

    /** @var list<array{0: int, 1: int}> opening windows in minutes after midnight */
    private const DEFAULT_WINDOWS = [[8 * 60, 12 * 60], [13 * 60, 19 * 60]];
    private const DEFAULT_DAYS = [1, 2, 3, 4, 5];

    public function __construct(
        private readonly OfficeHourRepository $officeHours,
        private readonly AppointmentRepository $appointments,
        private readonly ClockInterface $clock,
        private readonly ?OfficeUnavailableRepository $unavailability = null,
    ) {
    }

    /**
     * @return list<array{time: string, label: string, available: bool, reason: ?string}>
     */
    public function slotsFor(SasUnit $unit, Campus $campus, \DateTimeImmutable $day, ?Appointment $exclude = null): array
    {
        $zone = new \DateTimeZone(self::TIMEZONE);
        $start = $day->setTimezone($zone)->setTime(0, 0);
        $starts = $this->windowStarts($unit, (int) $start->format('N'));
        if (!$starts) {
            return [];
        }

        $taken = [];
        foreach ($this->appointments->occupiedStarts($unit, $campus, $this->storage($start), $this->storage($start->modify('+1 day')), $exclude) as $when) {
            $taken[$when->setTimezone($zone)->format('H:i')] = true;
        }

        $blockedPeriods = $this->unavailability?->periodsFor($unit, $start) ?? [];
        $now = $this->clock->now()->setTimezone($zone);
        $slots = [];
        foreach ($starts as $minutes) {
            $at = $start->setTime(intdiv($minutes, 60), $minutes % 60);
            $time = $at->format('H:i');
            $reason = null;
            $blocked = false;
            foreach ($blockedPeriods as $period) {
                if ($minutes < $period->getEndMinute() && $minutes + self::SLOT_MINUTES > $period->getStartMinute()) {
                    $blocked = true;
                    break;
                }
            }
            if ($blocked) {
                $reason = 'VPSAS' === $unit->getCode() ? 'VPSAS unavailable' : $unit->getName().' unavailable';
            } elseif ($at <= $now) {
                $reason = 'Time has passed';
            } elseif (isset($taken[$time])) {
                $reason = 'Already booked';
            }
            $slots[] = ['time' => $time, 'label' => $at->format('g:i A'), 'available' => null === $reason, 'reason' => $reason];
        }

        return $slots;
    }

    /** Returns why the time cannot be booked, or null when it is free. Days before today are left to the date rule. */
    public function problemWith(SasUnit $unit, Campus $campus, \DateTimeImmutable $when, ?Appointment $exclude = null, ?User $student = null): ?string
    {
        $zone = new \DateTimeZone(self::TIMEZONE);
        $local = $when->setTimezone($zone);
        if ($local->setTime(0, 0) < $this->clock->now()->setTimezone($zone)->setTime(0, 0)) {
            return null;
        }

        $slot = null;
        foreach ($this->slotsFor($unit, $campus, $local, $exclude) as $candidate) {
            if ($candidate['time'] === $local->format('H:i') && 0 === (int) $local->format('s')) {
                $slot = $candidate;
                break;
            }
        }

        if (null === $slot) {
            return \sprintf('%s is not open at that time. Please choose one of the available slots.', $unit->getName());
        }
        if ('Time has passed' === $slot['reason']) {
            return 'That time has already passed. Please choose a later slot.';
        }
        if ('Already booked' === $slot['reason']) {
            return 'That slot has already been booked. Please choose another time.';
        }
        if (!$slot['available']) {
            return $slot['reason'].'. Please choose another time or date.';
        }
        if ($student && $this->appointments->studentBusyAt($student, $this->storage($local), $exclude)) {
            return 'You already have an appointment at that time.';
        }

        return null;
    }

    /** @return list<int> start of every slot of the day, in minutes after midnight */
    private function windowStarts(SasUnit $unit, int $isoDay): array
    {
        $hours = $this->officeHours->forUnit($unit);
        if ($hours) {
            $windows = [];
            foreach ($hours as $hour) {
                if ($hour->getDayOfWeek() === $isoDay) {
                    $windows[] = [$hour->opensAtMinutes(), $hour->closesAtMinutes()];
                }
            }
        } else {
            $windows = \in_array($isoDay, self::DEFAULT_DAYS, true) ? self::DEFAULT_WINDOWS : [];
        }

        $starts = [];
        foreach ($windows as [$opens, $closes]) {
            for ($minutes = $opens; $minutes + self::SLOT_MINUTES <= $closes; $minutes += self::SLOT_MINUTES) {
                $starts[$minutes] = $minutes;
            }
        }
        sort($starts);

        return array_values($starts);
    }

    /** Appointments are stored in the PHP default time zone, so convert before comparing with the database. */
    private function storage(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone(new \DateTimeZone(date_default_timezone_get()));
    }
}
