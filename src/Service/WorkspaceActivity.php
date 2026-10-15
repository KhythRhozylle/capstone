<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\ServiceRequest;
use App\Enum\AppointmentStatus;
use App\Enum\RequestStatus;

class WorkspaceActivity
{
    /**
     * @param list<Appointment>     $appointments
     * @param list<Concern>         $concerns
     * @param list<ServiceRequest>  $services
     * @param list<DocumentRequest> $documents
     *
     * @return array{categories: list<array{label: string, icon: string, total: int, open: int, closed: int}>, total: int, open: int, closed: int, maximum: int}
     */
    public function summarize(array $appointments, array $concerns, array $services, array $documents): array
    {
        $categories = [];
        foreach ([
            ['Appointments', 'calendar', $appointments],
            ['Concerns', 'chat', $concerns],
            ['Service requests', 'request', $services],
            ['Documents', 'document', $documents],
        ] as [$label, $icon, $records]) {
            $closed = 0;
            foreach ($records as $record) {
                if (\in_array($record->getStatus(), [
                    AppointmentStatus::Completed,
                    AppointmentStatus::Cancelled,
                    AppointmentStatus::Rejected,
                    AppointmentStatus::NoShow,
                    RequestStatus::Resolved,
                    RequestStatus::Closed,
                    RequestStatus::Rejected,
                    RequestStatus::Released,
                ], true)) {
                    ++$closed;
                }
            }
            $categories[] = [
                'label' => $label,
                'icon' => $icon,
                'total' => \count($records),
                'open' => \count($records) - $closed,
                'closed' => $closed,
            ];
        }

        return [
            'categories' => $categories,
            'total' => array_sum(array_column($categories, 'total')),
            'open' => array_sum(array_column($categories, 'open')),
            'closed' => array_sum(array_column($categories, 'closed')),
            'maximum' => max(1, ...array_column($categories, 'open'), ...array_column($categories, 'closed')),
        ];
    }
}
