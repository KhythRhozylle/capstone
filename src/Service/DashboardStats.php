<?php

namespace App\Service;

use App\Entity\Feedback;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Enum\CareCaseStatus;
use App\Enum\ConcernType;
use App\Enum\RequestStatus;
use Doctrine\ORM\EntityManagerInterface;

class DashboardStats
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    /** @return array<string, mixed> */
    public function compile(): array
    {
        return [
            'students' => $this->count('App\Entity\User', 'u', 'JOIN u.studentProfile p'),
            'appointments' => $this->count('App\Entity\Appointment'),
            'concerns' => $this->count('App\Entity\Concern'),
            'complaints' => $this->count('App\Entity\Concern', 'c', '', 'c.type = :type', ['type' => ConcernType::Complaint]),
            'careCases' => $this->count('App\Entity\CareCase'),
            'referrals' => $this->count('App\Entity\Concern', 'c', '', 'c.referredToUnit IS NOT NULL')
                + $this->count('App\Entity\CareCase', 'c', '', 'c.sourceConcern IS NOT NULL'),
            'documents' => $this->count('App\Entity\DocumentRequest'),
            'serviceRequests' => $this->count('App\Entity\ServiceRequest'),
            'openConcerns' => $this->count('App\Entity\Concern', 'c', '', 'c.status NOT IN (:done)', [
                'done' => [RequestStatus::Resolved, RequestStatus::Closed, RequestStatus::Rejected],
            ]),
            'overdueFollowUps' => $this->count('App\Entity\CareFollowUp', 'f', '', 'f.completedAt IS NULL AND f.dueAt < :now', [
                'now' => new \DateTimeImmutable(),
            ]),
            'campusComparison' => $this->campusComparison(),
            'appointmentsByMode' => $this->appointmentsByMode(),
            'appointmentsByStatus' => $this->appointmentsByStatus(),
            'concernsByStatus' => $this->concernsByStatus(),
            'servicesByUnit' => $this->servicesByUnit(),
            'careByStatus' => $this->careByStatus(),
            'avgResolutionHours' => $this->avgResolutionHours(),
            'satisfaction' => $this->satisfaction(),
        ];
    }

    /** @return array{overall: array{average: float|null, responses: int, distribution: array<int, int>}, byUnit: list<array{unit: string, average: float, responses: int}>} */
    private function satisfaction(): array
    {
        $feedback = $this->em->getRepository(Feedback::class);

        return ['overall' => $feedback->overall(), 'byUnit' => $feedback->summaryByUnit()];
    }

    /** @param array<string, mixed> $params */
    private function count(string $class, string $alias = 'e', string $join = '', string $where = '', array $params = []): int
    {
        $dql = \sprintf('SELECT COUNT(%s.id) FROM %s %s %s', $alias, $class, $alias, $join);
        if ($where !== '') {
            $dql .= ' WHERE '.$where;
        }
        $query = $this->em->createQuery($dql);
        foreach ($params as $key => $value) {
            $query->setParameter($key, $value);
        }

        return (int) $query->getSingleScalarResult();
    }

    /** @return list<array{name: string, appointments: int, concerns: int, services: int, documents: int}> */
    private function campusComparison(): array
    {
        $campuses = $this->em->createQuery('SELECT c.id, c.name FROM App\Entity\Campus c ORDER BY c.name')->getResult();
        $totals = [];
        foreach (['appointments' => 'Appointment', 'concerns' => 'Concern', 'services' => 'ServiceRequest', 'documents' => 'DocumentRequest'] as $key => $entity) {
            $totals[$key] = array_column($this->em->createQuery(
                'SELECT IDENTITY(e.campus) AS campus, COUNT(e.id) AS total FROM App\\Entity\\'.$entity.' e GROUP BY e.campus'
            )->getArrayResult(), 'total', 'campus');
        }
        $rows = [];
        foreach ($campuses as $campus) {
            $row = ['name' => $campus['name']];
            foreach ($totals as $key => $counts) {
                $row[$key] = (int) ($counts[$campus['id']] ?? 0);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /** @return array<string, int> */
    private function appointmentsByMode(): array
    {
        return $this->groupedCounts('Appointment', 'mode', AppointmentMode::cases());
    }

    /** @return array<string, int> */
    private function appointmentsByStatus(): array
    {
        return $this->groupedCounts('Appointment', 'status', AppointmentStatus::cases());
    }

    /** @return array<string, int> */
    private function concernsByStatus(): array
    {
        return array_filter($this->groupedCounts('Concern', 'status', RequestStatus::cases()), static fn (int $count) => $count > 0);
    }

    /** @return array<string, int> */
    private function careByStatus(): array
    {
        return $this->groupedCounts('CareCase', 'status', CareCaseStatus::cases());
    }

    /** @param list<\BackedEnum> $cases
     * @return array<string, int>
     */
    private function groupedCounts(string $entity, string $field, array $cases): array
    {
        $counts = [];
        foreach ($this->em->createQuery('SELECT e.'.$field.' AS value, COUNT(e.id) AS total FROM App\\Entity\\'.$entity.' e GROUP BY e.'.$field)->getArrayResult() as $row) {
            $value = $row['value'] instanceof \BackedEnum ? $row['value']->value : $row['value'];
            $counts[$value] = (int) $row['total'];
        }
        $out = [];
        foreach ($cases as $case) {
            $out[$case->label()] = $counts[$case->value] ?? 0;
        }

        return $out;
    }

    /** @return list<array{name: string, total: int}> */
    private function servicesByUnit(): array
    {
        return $this->em->createQuery(
            'SELECT u.name AS name, COUNT(r.id) AS total FROM App\Entity\ServiceRequest r JOIN r.unit u GROUP BY u.id, u.name ORDER BY total DESC'
        )->getResult();
    }

    private function avgResolutionHours(): ?float
    {
        /** @var list<array{createdAt: \DateTimeImmutable, resolvedAt: \DateTimeImmutable}> $rows */
        $rows = $this->em->createQuery('SELECT c.createdAt, c.resolvedAt FROM App\Entity\Concern c WHERE c.resolvedAt IS NOT NULL')->getResult();
        if ($rows === []) {
            return null;
        }
        $hours = [];
        foreach ($rows as $concern) {
            $resolved = $concern['resolvedAt'];
            if ($resolved instanceof \DateTimeImmutable) {
                $hours[] = ($resolved->getTimestamp() - $concern['createdAt']->getTimestamp()) / 3600;
            }
        }

        return $hours === [] ? null : round(array_sum($hours) / \count($hours), 1);
    }
}
