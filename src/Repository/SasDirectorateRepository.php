<?php

namespace App\Repository;

use App\Entity\SasDirectorate;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SasDirectorate> */
class SasDirectorateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SasDirectorate::class);
    }
}
