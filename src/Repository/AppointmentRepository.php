<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Appointment> */
class AppointmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Appointment::class);
    }

    /** @return list<Appointment> */
    public function findForStudent(User $student): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.student = :student')
            ->setParameter('student', $student)
            ->orderBy('a.scheduledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Appointment> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus): array
    {
        $qb = $this->createQueryBuilder('a')->orderBy('a.scheduledAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('a.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('a.campus = :campus')->setParameter('campus', $campus);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Appointment> */
    public function scheduledBetween(?SasUnit $unit, ?Campus $campus, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.scheduledAt >= :from')
            ->andWhere('a.scheduledAt < :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('a.scheduledAt', 'ASC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('a.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('a.campus = :campus')->setParameter('campus', $campus);
        }

        return $qb->getQuery()->getResult();
    }
}
