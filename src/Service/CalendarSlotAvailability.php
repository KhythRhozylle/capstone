<?php

namespace App\Service;

use App\Entity\OfficeUnavailable;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Repository\OfficeUnavailableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class CalendarSlotAvailability
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly OfficeUnavailableRepository $periods,
        private readonly LockFactory $locks,
    ) {
    }

    /** Blocks or reopens a whole day at once, replacing any hourly blocks already set on it. */
    public function setWholeDay(SasUnit $unit, User $owner, \DateTimeImmutable $date, bool $unavailable): void
    {
        $lock = $this->locks->createLock('office-availability-'.$unit->getId().'-'.$date->format('Y-m-d'));
        if (!$lock->acquire()) {
            throw new ConflictHttpException('Availability is being updated. Please try again.');
        }
        try {
            $this->manager->wrapInTransaction(function () use ($unit, $owner, $date, $unavailable): void {
                $existing = $this->periods->periodsFor($unit, $date);
                foreach ($existing as $entry) {
                    if ($entry->getOwner()->getId() !== $owner->getId()) {
                        throw new AccessDeniedException();
                    }
                    $this->manager->remove($entry);
                }
                $this->manager->flush();
                if ($unavailable) {
                    $entry = new OfficeUnavailable($unit, $owner);
                    $entry->setDay($date);
                    $entry->setStartMinute(0);
                    $entry->setEndMinute(1440);
                    $this->manager->persist($entry);
                }
            });
        } finally {
            $lock->release();
        }
    }

    public function set(SasUnit $unit, User $owner, \DateTimeImmutable $date, int $start, bool $unavailable): void
    {
        $lock = $this->locks->createLock('office-availability-'.$unit->getId().'-'.$date->format('Y-m-d'));
        if (!$lock->acquire()) {
            throw new ConflictHttpException('Availability is being updated. Please try again.');
        }
        try {
            $this->manager->wrapInTransaction(function () use ($unit, $owner, $date, $start, $unavailable): void {
                $end = $start + 60;
                $overlapping = array_filter($this->periods->periodsFor($unit, $date), static fn (OfficeUnavailable $entry) => $entry->getStartMinute() < $end && $entry->getEndMinute() > $start);
                foreach ($overlapping as $entry) {
                    if ($entry->getOwner()->getId() !== $owner->getId()) {
                        throw new AccessDeniedException();
                    }
                    if ($unavailable && $entry->getStartMinute() <= $start && $entry->getEndMinute() >= $end) {
                        return;
                    }
                }
                $remaining = [];
                foreach ($overlapping as $entry) {
                    if ($unavailable) {
                        $start = min($start, $entry->getStartMinute());
                        $end = max($end, $entry->getEndMinute());
                    } else {
                        if ($entry->getStartMinute() < $start) {
                            $remaining[] = [$entry->getStartMinute(), $start];
                        }
                        if ($entry->getEndMinute() > $end) {
                            $remaining[] = [$end, $entry->getEndMinute()];
                        }
                    }
                    $this->manager->remove($entry);
                }
                $this->manager->flush();
                if ($unavailable) {
                    $remaining[] = [$start, $end];
                }
                foreach ($remaining as [$from, $until]) {
                    $entry = new OfficeUnavailable($unit, $owner);
                    $entry->setDay($date);
                    $entry->setStartMinute($from);
                    $entry->setEndMinute($until);
                    $this->manager->persist($entry);
                }
            });
        } finally {
            $lock->release();
        }
    }
}
