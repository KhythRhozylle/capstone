<?php

namespace App\Repository;

use App\Entity\SasUnit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SasUnit> */
class SasUnitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SasUnit::class);
    }

    public function findOneByCode(string $code): ?SasUnit
    {
        return $this->findOneBy(['code' => $code]);
    }
}
