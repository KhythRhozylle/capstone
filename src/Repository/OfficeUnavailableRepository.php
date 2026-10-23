<?php

namespace App\Repository;

use App\Entity\OfficeUnavailable;
use App\Entity\SasUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OfficeUnavailable> */
class OfficeUnavailableRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficeUnavailable::class);
    }

    public function isBlocked(SasUnit $unit, \DateTimeImmutable $day): bool
    {
        return null !== $this->findOneBy(['unit' => $unit, 'day' => $day, 'startMinute' => 0, 'endMinute' => 1440]);
    }

    /** @return list<OfficeUnavailable> */
    public function periodsFor(SasUnit $unit, \DateTimeImmutable $day): array
    {
        return $this->findBy(['unit' => $unit, 'day' => $day], ['startMinute' => 'ASC']);
    }

    public function overlaps(SasUnit $unit, \DateTimeImmutable $day, int $start, int $end): bool
    {
        return (int) $this->createQueryBuilder('u')->select('COUNT(u.id)')
            ->where('u.unit = :unit AND u.day = :day AND u.startMinute < :end AND u.endMinute > :start')
            ->setParameter('unit', $unit)->setParameter('day', $day, \Doctrine\DBAL\Types\Types::DATE_IMMUTABLE)
            ->setParameter('start', $start)->setParameter('end', $end)->getQuery()->getSingleScalarResult() > 0;
    }

    public function periods(SasUnit $unit): array
    {
        return array_map(static fn (OfficeUnavailable $entry) => ['date' => $entry->getDay()->format('Y-m-d'), 'start' => $entry->getStartMinute(), 'end' => $entry->getEndMinute()], $this->findBy(['unit' => $unit], ['day' => 'ASC', 'startMinute' => 'ASC']));
    }

    /** @return list<string> */
    public function dates(SasUnit $unit): array
    {
        return array_map(static fn (OfficeUnavailable $entry) => $entry->getDay()->format('Y-m-d'), $this->findBy(['unit' => $unit, 'startMinute' => 0, 'endMinute' => 1440], ['day' => 'ASC']));
    }
}
