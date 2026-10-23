<?php

namespace App\Repository;

use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\DocumentRequest;
use App\Entity\Feedback;
use App\Entity\ServiceRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Feedback> */
class FeedbackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Feedback::class);
    }

    public function forSubject(Appointment|ServiceRequest|DocumentRequest $subject): ?Feedback
    {
        $field = match (true) {
            $subject instanceof Appointment => 'appointment',
            $subject instanceof ServiceRequest => 'serviceRequest',
            $subject instanceof DocumentRequest => 'documentRequest',
        };

        return $this->findOneBy([$field => $subject]);
    }

    /**
     * Average rating and number of responses for each unit that has been rated, best rated first.
     *
     * @return list<array{unit: string, average: float, responses: int}>
     */
    public function summaryByUnit(): array
    {
        $rows = $this->getEntityManager()->createQuery(
            'SELECT u.name AS unit, AVG(f.rating) AS average, COUNT(f.id) AS responses
             FROM App\Entity\Feedback f JOIN f.unit u
             GROUP BY u.id, u.name
             ORDER BY average DESC, responses DESC'
        )->getArrayResult();

        return array_map(static fn (array $row): array => [
            'unit' => (string) $row['unit'],
            'average' => round((float) $row['average'], 2),
            'responses' => (int) $row['responses'],
        ], $rows);
    }

    /** @return list<Feedback> */
    public function forUnits(?array $unitIds, ?Campus $campus = null): array
    {
        $query = $this->createQueryBuilder('f')->orderBy('f.createdAt', 'DESC')->setMaxResults(100);
        if (null !== $unitIds) {
            $query->andWhere('IDENTITY(f.unit) IN (:units)')->setParameter('units', $unitIds ?: [-1]);
        }

        if ($campus) {
            $query->join('f.student', 'student')->andWhere('student.campus = :campus')->setParameter('campus', $campus);
        }

        return $query->getQuery()->getResult();
    }

    /** @return array{average: float|null, responses: int, distribution: array<int, int>} */
    public function overall(?array $unitIds = null, ?Campus $campus = null): array
    {
        $distribution = array_fill_keys([5, 4, 3, 2, 1], 0);
        $query = $this->createQueryBuilder('f')->select('f.rating AS rating, COUNT(f.id) AS total')->groupBy('f.rating');
        if (null !== $unitIds) {
            $query->andWhere('IDENTITY(f.unit) IN (:units)')->setParameter('units', $unitIds ?: [-1]);
        }
        if ($campus) {
            $query->join('f.student', 'student')->andWhere('student.campus = :campus')->setParameter('campus', $campus);
        }
        $rows = $query->getQuery()->getArrayResult();
        $sum = 0;
        $count = 0;
        foreach ($rows as $row) {
            $distribution[(int) $row['rating']] = (int) $row['total'];
            $sum += (int) $row['rating'] * (int) $row['total'];
            $count += (int) $row['total'];
        }

        return [
            'average' => $count ? round($sum / $count, 2) : null,
            'responses' => $count,
            'distribution' => $distribution,
        ];
    }
}
