<?php

namespace App\Repository;

use App\Entity\CareCase;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<CareCase> */
class CareCaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CareCase::class);
    }

    /** @return list<CareCase> */
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
