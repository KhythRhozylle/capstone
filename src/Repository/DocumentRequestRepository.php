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
    public function findForStaff(?SasUnit $unit, ?Campus $campus): array
    {
        $qb = $this->createQueryBuilder('d')->orderBy('d.createdAt', 'DESC');
        if ($unit instanceof SasUnit) {
            $qb->andWhere('d.unit = :unit')->setParameter('unit', $unit);
        }
        if ($campus instanceof Campus) {
            $qb->andWhere('d.campus = :campus')->setParameter('campus', $campus);
        }

        return $qb->getQuery()->getResult();
    }
}
