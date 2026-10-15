<?php

namespace App\Tests\Service;

use App\Entity\Appointment;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\ServiceRequest;
use App\Enum\AppointmentStatus;
use App\Enum\RequestStatus;
use App\Service\WorkspaceActivity;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class WorkspaceActivityTest extends KernelTestCase
{
    public function testEmptyWorkspaceProducesSafeChartValues(): void
    {
        $activity = static::getContainer()->get(WorkspaceActivity::class)->summarize([], [], [], []);

        self::assertSame(['Appointments', 'Concerns', 'Service requests', 'Documents'], array_column($activity['categories'], 'label'));
        self::assertSame([0, 0, 0, 0], array_column($activity['categories'], 'total'));
        self::assertSame([0, 0, 0, 0], array_column($activity['categories'], 'open'));
        self::assertSame([0, 0, 0, 0], array_column($activity['categories'], 'closed'));
        self::assertSame(0, $activity['total']);
        self::assertSame(0, $activity['open']);
        self::assertSame(0, $activity['closed']);
        self::assertSame(1, $activity['maximum']);
    }

    public function testTotalsIncludeEveryRecordBeyondTheRecentDisplayLimit(): void
    {
        $appointments = [];
        for ($i = 0; $i < 12; ++$i) {
            $appointments[] = (new Appointment())->setStatus($i < 9 ? AppointmentStatus::Approved : AppointmentStatus::Completed);
        }
        $concerns = [
            (new Concern())->setStatus(RequestStatus::Submitted),
            (new Concern())->setStatus(RequestStatus::Resolved),
        ];
        $services = [
            (new ServiceRequest())->setStatus(RequestStatus::Processing),
            (new ServiceRequest())->setStatus(RequestStatus::Closed),
            (new ServiceRequest())->setStatus(RequestStatus::Rejected),
        ];
        $documents = [
            (new DocumentRequest())->setStatus(RequestStatus::Ready),
            (new DocumentRequest())->setStatus(RequestStatus::Released),
        ];

        $activity = static::getContainer()->get(WorkspaceActivity::class)->summarize($appointments, $concerns, $services, $documents);

        self::assertSame([12, 2, 3, 2], array_column($activity['categories'], 'total'));
        self::assertSame([9, 1, 1, 1], array_column($activity['categories'], 'open'));
        self::assertSame([3, 1, 2, 1], array_column($activity['categories'], 'closed'));
        self::assertSame(19, $activity['total']);
        self::assertSame(12, $activity['open']);
        self::assertSame(7, $activity['closed']);
        self::assertSame(9, $activity['maximum']);
    }

    #[DataProvider('appointmentStatuses')]
    public function testAppointmentStatusClassification(AppointmentStatus $status, bool $closed): void
    {
        $activity = static::getContainer()->get(WorkspaceActivity::class)->summarize([(new Appointment())->setStatus($status)], [], [], []);

        self::assertSame(1, $activity['categories'][0]['total']);
        self::assertSame($closed ? 0 : 1, $activity['categories'][0]['open']);
        self::assertSame($closed ? 1 : 0, $activity['categories'][0]['closed']);
    }

    public static function appointmentStatuses(): iterable
    {
        yield 'pending approval' => [AppointmentStatus::Pending, false];
        yield 'approved visit' => [AppointmentStatus::Approved, false];
        yield 'rescheduled visit' => [AppointmentStatus::Rescheduled, false];
        yield 'checked-in visit' => [AppointmentStatus::CheckedIn, false];
        yield 'completed visit' => [AppointmentStatus::Completed, true];
        yield 'cancelled visit' => [AppointmentStatus::Cancelled, true];
        yield 'rejected visit' => [AppointmentStatus::Rejected, true];
        yield 'missed visit' => [AppointmentStatus::NoShow, true];
    }

    #[DataProvider('requestStatuses')]
    public function testRequestStatusClassificationAcrossAllRequestCategories(RequestStatus $status, bool $closed): void
    {
        $activity = static::getContainer()->get(WorkspaceActivity::class)->summarize(
            [],
            [(new Concern())->setStatus($status)],
            [(new ServiceRequest())->setStatus($status)],
            [(new DocumentRequest())->setStatus($status)],
        );

        foreach (array_slice($activity['categories'], 1) as $category) {
            self::assertSame(1, $category['total']);
            self::assertSame($closed ? 0 : 1, $category['open']);
            self::assertSame($closed ? 1 : 0, $category['closed']);
        }
    }

    public static function requestStatuses(): iterable
    {
        yield 'submitted request' => [RequestStatus::Submitted, false];
        yield 'processing request' => [RequestStatus::Processing, false];
        yield 'referred request' => [RequestStatus::Referred, false];
        yield 'request in progress' => [RequestStatus::InProgress, false];
        yield 'document awaiting release' => [RequestStatus::Ready, false];
        yield 'resolved request' => [RequestStatus::Resolved, true];
        yield 'closed request' => [RequestStatus::Closed, true];
        yield 'rejected request' => [RequestStatus::Rejected, true];
        yield 'released document' => [RequestStatus::Released, true];
    }
}
