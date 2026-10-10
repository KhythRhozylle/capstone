<?php

namespace App\Repository;

use App\Entity\ServiceRequest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<ServiceRequest> */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    /** @return list<ServiceRequest> */
    public function findForStudent(User $student): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.student = :student')
            ->setParameter('student', $student)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
