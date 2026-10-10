<?php

namespace App\Repository;

use App\Entity\StatusHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<StatusHistory> */
class StatusHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StatusHistory::class);
    }

    /** @return list<StatusHistory> */
    public function forEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.entityType = :type')
            ->andWhere('h.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('h.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
