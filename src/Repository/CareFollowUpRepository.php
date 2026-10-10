<?php

namespace App\Repository;

use App\Entity\CareFollowUp;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CareFollowUp> */
class CareFollowUpRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CareFollowUp::class);
    }

    /** @return list<CareFollowUp> */
    public function overdue(): array
    {
        return $this->createQueryBuilder('f')
            ->andWhere('f.completedAt IS NULL')
            ->andWhere('f.dueAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('f.dueAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
