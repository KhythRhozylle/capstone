<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\Concern;
use App\Entity\SasUnit;
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

    /** @return list<Concern> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus): array
    {
        $qb = $this->createQueryBuilder('c')->orderBy('c.createdAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('c.assignedUnit = :unit OR c.referredToUnit = :unit')
                ->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('c.campus = :campus')->setParameter('campus', $campus);
        }

        return $qb->getQuery()->getResult();
    }
}
