<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\QueueTicket;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\QueueStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<QueueTicket> */
class QueueTicketRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QueueTicket::class);
    }

    public function nextNumber(Campus $campus, SasUnit $unit): int
    {
        $today = new \DateTimeImmutable('today');
        $max = $this->createQueryBuilder('q')
            ->select('MAX(q.number)')
            ->andWhere('q.campus = :campus')
            ->andWhere('q.unit = :unit')
            ->andWhere('q.createdAt >= :today')
            ->setParameter('campus', $campus)
            ->setParameter('unit', $unit)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int) $max) + 1;
    }

    public function nextWaiting(Campus $campus, SasUnit $unit): ?QueueTicket
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.campus = :campus')
            ->andWhere('q.unit = :unit')
            ->andWhere('q.status = :status')
            ->setParameter('campus', $campus)
            ->setParameter('unit', $unit)
            ->setParameter('status', QueueStatus::Waiting)
            ->orderBy('q.number', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<QueueTicket> */
    public function todayFor(Campus $campus, SasUnit $unit): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.campus = :campus')
            ->andWhere('q.unit = :unit')
            ->andWhere('q.createdAt >= :today')
            ->setParameter('campus', $campus)
            ->setParameter('unit', $unit)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('q.number', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<QueueTicket> */
    public function todayForStudent(User $student): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.student = :student')
            ->andWhere('q.createdAt >= :today')
            ->setParameter('student', $student)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
