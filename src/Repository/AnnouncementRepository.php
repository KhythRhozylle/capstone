<?php

namespace App\Repository;

use App\Entity\Announcement;
use App\Entity\Campus;
use App\Enum\AnnouncementScope;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Announcement> */
class AnnouncementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Announcement::class);
    }

    /** @return list<Announcement> */
    public function visibleFor(?Campus $campus, int $limit = 20): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        if ($campus instanceof Campus) {
            $qb->andWhere('a.scope = :university OR (a.scope = :campusScope AND a.campus = :campus)')
                ->setParameter('university', AnnouncementScope::University)
                ->setParameter('campusScope', AnnouncementScope::Campus)
                ->setParameter('campus', $campus);
        }

        return $qb->getQuery()->getResult();
    }
}
