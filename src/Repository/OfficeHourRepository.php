<?php

namespace App\Repository;

use App\Entity\OfficeHour;
use App\Entity\SasUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<OfficeHour> */
class OfficeHourRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OfficeHour::class);
    }

    /** @return list<OfficeHour> */
    public function forUnit(SasUnit $unit): array
    {
        return $this->createQueryBuilder('h')
            ->andWhere('h.unit = :unit')
            ->setParameter('unit', $unit)
            ->orderBy('h.dayOfWeek', 'ASC')
            ->addOrderBy('h.opensAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
