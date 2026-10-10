<?php

namespace App\Service;

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
        ];
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
        $rows = [];
        foreach ($campuses as $campus) {
            $id = $campus['id'];
            $rows[] = [
                'name' => $campus['name'],
                'appointments' => $this->count('App\Entity\Appointment', 'e', '', 'IDENTITY(e.campus) = :id', ['id' => $id]),
                'concerns' => $this->count('App\Entity\Concern', 'e', '', 'IDENTITY(e.campus) = :id', ['id' => $id]),
                'services' => $this->count('App\Entity\ServiceRequest', 'e', '', 'IDENTITY(e.campus) = :id', ['id' => $id]),
                'documents' => $this->count('App\Entity\DocumentRequest', 'e', '', 'IDENTITY(e.campus) = :id', ['id' => $id]),
            ];
        }

        return $rows;
    }

    /** @return array<string, int> */
    private function appointmentsByMode(): array
    {
        $out = [];
        foreach (AppointmentMode::cases() as $mode) {
            $out[$mode->label()] = $this->count('App\Entity\Appointment', 'e', '', 'e.mode = :m', ['m' => $mode]);
        }

        return $out;
    }

    /** @return array<string, int> */
    private function appointmentsByStatus(): array
    {
        $out = [];
        foreach (AppointmentStatus::cases() as $status) {
            $out[$status->label()] = $this->count('App\Entity\Appointment', 'e', '', 'e.status = :s', ['s' => $status]);
        }

        return $out;
    }

    /** @return array<string, int> */
    private function concernsByStatus(): array
    {
        $out = [];
        foreach (RequestStatus::cases() as $status) {
            $n = $this->count('App\Entity\Concern', 'e', '', 'e.status = :s', ['s' => $status]);
            if ($n > 0) {
                $out[$status->label()] = $n;
            }
        }

        return $out;
    }

    /** @return array<string, int> */
    private function careByStatus(): array
    {
        $out = [];
        foreach (CareCaseStatus::cases() as $status) {
            $out[$status->label()] = $this->count('App\Entity\CareCase', 'e', '', 'e.status = :s', ['s' => $status]);
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
        /** @var list<\App\Entity\Concern> $rows */
        $rows = $this->em->createQuery('SELECT c FROM App\Entity\Concern c WHERE c.resolvedAt IS NOT NULL')->getResult();
        if ($rows === []) {
            return null;
        }
        $hours = [];
        foreach ($rows as $concern) {
            $resolved = $concern->getResolvedAt();
            if ($resolved instanceof \DateTimeImmutable) {
                $hours[] = ($resolved->getTimestamp() - $concern->getCreatedAt()->getTimestamp()) / 3600;
            }
        }

        return $hours === [] ? null : round(array_sum($hours) / \count($hours), 1);
    }
}
