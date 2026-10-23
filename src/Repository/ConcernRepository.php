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
            ->leftJoin('c.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('c.assignedUnit', 'display_assignedUnit')->addSelect('display_assignedUnit')
            ->leftJoin('c.referredToUnit', 'display_referredToUnit')->addSelect('display_referredToUnit')
            ->andWhere('c.student = :student')
            ->setParameter('student', $student)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Concern> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus, ?string $directorateCode = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('c.assignedUnit', 'display_assignedUnit')->addSelect('display_assignedUnit')
            ->leftJoin('c.referredToUnit', 'display_referredToUnit')->addSelect('display_referredToUnit')
            ->leftJoin('c.student', 'display_student')->addSelect('display_student')
            ->leftJoin('display_student.studentProfile', 'display_profile')->addSelect('display_profile')
            ->orderBy('c.createdAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('c.assignedUnit = :unit OR c.referredToUnit = :unit')
                ->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('c.campus = :campus')->setParameter('campus', $campus);
        }

        if (null !== $directorateCode) {
            $qb->leftJoin('c.assignedUnit', 'assigned')->leftJoin('assigned.directorate', 'assigned_dir')
                ->leftJoin('c.referredToUnit', 'referred')->leftJoin('referred.directorate', 'referred_dir')
                ->andWhere('(assigned_dir.code = :directorate AND assigned.code != :vpsas) OR (referred_dir.code = :directorate AND referred.code != :vpsas)')
                ->setParameter('directorate', $directorateCode)->setParameter('vpsas', 'VPSAS');
        }

        return $qb->getQuery()->getResult();
    }
}
