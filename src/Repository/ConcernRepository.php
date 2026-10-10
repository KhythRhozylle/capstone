<?php

namespace App\Repository;

use App\Entity\Concern;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Concern> */
class ConcernRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Concern::class);
    }

    /** @return list<Concern> */
    public function findForStudent(User $student): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.student = :student')
            ->setParameter('student', $student)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
