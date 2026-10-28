<?php

namespace App\Repository;

use App\Entity\OfficeDocumentColumn;
use App\Entity\User;
use App\Service\StaffScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OfficeDocumentColumn> */
class OfficeDocumentColumnRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficeDocumentColumn::class);
    }

    /** @return list<OfficeDocumentColumn> */
    public function forOfficial(User $user): array
    {
        $scope = StaffScope::for($user);
        $query = $this->createQueryBuilder('r')
            ->join('r.unit', 'u')->addSelect('u')
            ->orderBy('r.id', 'ASC');
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

    /** @return list<OfficeDocumentColumn> */
    public function forUnit(\App\Entity\SasUnit $unit): array
    {
        return $this->findBy(['unit' => $unit], ['id' => 'ASC']);
    }
}
