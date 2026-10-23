<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\AppointmentStatus;
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
            ->leftJoin('a.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('a.unit', 'display_unit')->addSelect('display_unit')
            ->andWhere('a.student = :student')
            ->setParameter('student', $student)
            ->orderBy('a.scheduledAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Appointment> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus, ?string $directorateCode = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('a.unit', 'display_unit')->addSelect('display_unit')
            ->leftJoin('a.student', 'display_student')->addSelect('display_student')
            ->leftJoin('display_student.studentProfile', 'display_profile')->addSelect('display_profile')
            ->orderBy('a.scheduledAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('a.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('a.campus = :campus')->setParameter('campus', $campus);
        }

        if (null !== $directorateCode) {
            $qb->join('a.unit', 'scope_unit')->join('scope_unit.directorate', 'scope_directorate')
                ->andWhere('scope_directorate.code = :directorate')->andWhere('scope_unit.code != :vpsas')
                ->setParameter('directorate', $directorateCode)->setParameter('vpsas', 'VPSAS');
        }

        return $qb->getQuery()->getResult();
    }

    /** @return list<Appointment> */
    public function scheduledBetween(?SasUnit $unit, ?Campus $campus, \DateTimeImmutable $from, \DateTimeImmutable $to, ?string $directorateCode = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->leftJoin('a.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('a.unit', 'display_unit')->addSelect('display_unit')
            ->leftJoin('a.student', 'display_student')->addSelect('display_student')
            ->leftJoin('display_student.studentProfile', 'display_profile')->addSelect('display_profile')
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

        if (null !== $directorateCode) {
            $qb->join('a.unit', 'scope_unit')->join('scope_unit.directorate', 'scope_directorate')
                ->andWhere('scope_directorate.code = :directorate')->andWhere('scope_unit.code != :vpsas')
                ->setParameter('directorate', $directorateCode)->setParameter('vpsas', 'VPSAS');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Start times of the appointments that currently hold a slot of a unit on a campus.
     *
     * @return list<\DateTimeImmutable>
     */
    public function occupiedStarts(SasUnit $unit, Campus $campus, \DateTimeImmutable $from, \DateTimeImmutable $to, ?Appointment $exclude = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.scheduledAt')
            ->andWhere('a.unit = :unit')
            ->andWhere('a.campus = :campus')
            ->andWhere('a.scheduledAt >= :from')
            ->andWhere('a.scheduledAt < :to')
            ->andWhere('a.status IN (:active)')
            ->setParameter('unit', $unit)
            ->setParameter('campus', $campus)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('active', AppointmentStatus::occupying());
        if ($exclude?->getId()) {
            $qb->andWhere('a.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        return array_column($qb->getQuery()->getArrayResult(), 'scheduledAt');
    }

    public function studentBusyAt(User $student, \DateTimeImmutable $at, ?Appointment $exclude = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.student = :student')
            ->andWhere('a.scheduledAt = :at')
            ->andWhere('a.status IN (:active)')
            ->setParameter('student', $student)
            ->setParameter('at', $at)
            ->setParameter('active', AppointmentStatus::occupying());
        if ($exclude?->getId()) {
            $qb->andWhere('a.id != :exclude')->setParameter('exclude', $exclude->getId());
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
