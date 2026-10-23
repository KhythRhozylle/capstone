<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\SasUnit;
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
            ->leftJoin('r.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('r.unit', 'display_unit')->addSelect('display_unit')
            ->andWhere('r.student = :student')
            ->setParameter('student', $student)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<ServiceRequest> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus, ?string $directorateCode = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('r.unit', 'display_unit')->addSelect('display_unit')
            ->leftJoin('r.student', 'display_student')->addSelect('display_student')
            ->leftJoin('display_student.studentProfile', 'display_profile')->addSelect('display_profile')
            ->orderBy('r.createdAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('r.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('r.campus = :campus')->setParameter('campus', $campus);
        }

        if (null !== $directorateCode) {
            $qb->join('r.unit', 'scope_unit')->join('scope_unit.directorate', 'scope_directorate')
                ->andWhere('scope_directorate.code = :directorate')->andWhere('scope_unit.code != :vpsas')
                ->setParameter('directorate', $directorateCode)->setParameter('vpsas', 'VPSAS');
        }

        return $qb->getQuery()->getResult();
    }
}
