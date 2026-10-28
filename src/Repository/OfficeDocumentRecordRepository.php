<?php

namespace App\Repository;

use App\Entity\OfficeDocumentRecord;
use App\Entity\User;
use App\Service\StaffScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OfficeDocumentRecord> */
class OfficeDocumentRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficeDocumentRecord::class);
    }

    /** @return list<OfficeDocumentRecord> */
    public function forOfficial(User $user): array
    {
        $scope = StaffScope::for($user);
        $query = $this->createQueryBuilder('r')
            ->join('r.unit', 'u')->addSelect('u')
            ->join('r.campus', 'c')->addSelect('c')
            ->orderBy('r.receivedAt', 'DESC')->addOrderBy('r.id', 'DESC');
        if ($scope->directorateCode) {
            $query->join('u.directorate', 'd')->andWhere('d.code = :directorate')->andWhere('u.code != :vpsas')
                ->setParameter('directorate', $scope->directorateCode)->setParameter('vpsas', 'VPSAS');
        } elseif (!$scope->unrestricted) {
            if (null === $scope->unit) {
                return [];
            }
            $query->andWhere('r.unit = :unit')->setParameter('unit', $scope->unit);
        }

        return $query->getQuery()->getResult();
    }
}
