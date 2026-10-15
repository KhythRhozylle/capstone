<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\CareFollowUp;
use App\Entity\User;
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
    public function overdueFor(User $user): array
    {
        $qb = $this->createQueryBuilder('f')
            ->join('f.careCase', 'c')
            ->andWhere('f.completedAt IS NULL')
            ->andWhere('f.dueAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('f.dueAt', 'ASC');

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
