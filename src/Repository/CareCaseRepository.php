<?php

namespace App\Repository;

use App\Entity\Campus;
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

    /** @return list<CareCase> */
    public function findForDesk(User $user): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.createdAt', 'DESC');
        $roles = $user->getRoles();
        $unrestricted = \in_array('ROLE_VPSAS', $roles, true)
            || \in_array('ROLE_DIRECTOR', $roles, true)
            || \in_array('ROLE_VPSAS_SECRETARIAT', $roles, true);
        if (!$unrestricted && $user->getCampus() instanceof Campus) {
            $qb->andWhere('c.campus = :campus')->setParameter('campus', $user->getCampus());
        }

        return $qb->getQuery()->getResult();
    }
}
