<?php

namespace App\Repository;

use App\Entity\Campus;
use App\Entity\DocumentRequest;
use App\Entity\SasUnit;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<DocumentRequest> */
class DocumentRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentRequest::class);
    }

    /** @return list<DocumentRequest> */
    public function findForStudent(User $student): array
    {
        return $this->createQueryBuilder('d')
            ->leftJoin('d.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('d.unit', 'display_unit')->addSelect('display_unit')
            ->andWhere('d.student = :student')
            ->setParameter('student', $student)
            ->orderBy('d.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOneByToken(string $token): ?DocumentRequest
    {
        return $this->findOneBy(['verificationToken' => $token]);
    }

    /** @return list<DocumentRequest> */
    public function findForStaff(?SasUnit $unit, ?Campus $campus, ?string $directorateCode = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->leftJoin('d.campus', 'display_campus')->addSelect('display_campus')
            ->leftJoin('d.unit', 'display_unit')->addSelect('display_unit')
            ->leftJoin('d.student', 'display_student')->addSelect('display_student')
            ->leftJoin('display_student.studentProfile', 'display_profile')->addSelect('display_profile')
            ->orderBy('d.createdAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('d.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('d.campus = :campus')->setParameter('campus', $campus);
        }

        if (null !== $directorateCode) {
            $qb->join('d.unit', 'scope_unit')->join('scope_unit.directorate', 'scope_directorate')
                ->andWhere('scope_directorate.code = :directorate')->andWhere('scope_unit.code != :vpsas')
                ->setParameter('directorate', $directorateCode)->setParameter('vpsas', 'VPSAS');
        }

        return $qb->getQuery()->getResult();
    }
}
