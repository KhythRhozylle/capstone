<?php

namespace App\Tests\Service;

use App\Entity\Campus;
use App\Entity\OfficeHour;
use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use App\Repository\AppointmentRepository;
use App\Repository\OfficeHourRepository;
use App\Service\AppointmentAvailability;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

class AppointmentAvailabilityTest extends TestCase
{
    private const MANILA = 'Asia/Manila';

    private SasUnit $unit;
    private Campus $campus;

    protected function setUp(): void
    {
        $this->unit = new SasUnit('OSAS', 'University OSAS', new SasDirectorate('SDS', 'Student Development Services'));
        $this->campus = new Campus('MAIN', 'Dumaguete Main Campus');
    }

    public function testUnitsWithoutOfficeHoursFallBackToWeekdayHoursWithALunchBreak(): void
    {
        $slots = $this->availability()->slotsFor($this->unit, $this->campus, $this->manila('2026-10-21'));

        self::assertSame(['08:00', '09:00', '10:00', '11:00', '13:00', '14:00', '15:00', '16:00', '17:00', '18:00'], array_column($slots, 'time'));
        self::assertNotContains(false, array_column($slots, 'available'));
    }

    public function testWeekendsAreClosedByDefault(): void
    {
        self::assertSame([], $this->availability()->slotsFor($this->unit, $this->campus, $this->manila('2026-10-24')));
    }

    public function testConfiguredOfficeHoursReplaceTheDefaults(): void
    {
        $tuesday = (new OfficeHour($this->unit))->setDayOfWeek(2)->setOpensAt(new \DateTimeImmutable('09:00'))->setClosesAt(new \DateTimeImmutable('11:30'));
        $availability = $this->availability(hours: [$tuesday]);

        self::assertSame(['09:00', '10:00'], array_column($availability->slotsFor($this->unit, $this->campus, $this->manila('2026-10-20')), 'time'));
        self::assertSame([], $availability->slotsFor($this->unit, $this->campus, $this->manila('2026-10-21')), 'Wednesday is not configured, so it is closed');
    }

    public function testBookedAndPastSlotsAreNotAvailable(): void
    {
        // 10:00 Manila on Wednesday is already taken.
        $taken = [new \DateTimeImmutable('2026-10-21 02:00:00', new \DateTimeZone('UTC'))];

        // With the clock at 11:30 Manila that day, the morning has passed.
        $late = $this->availability(taken: $taken, now: '2026-10-21 03:30:00');
        $slots = array_column($late->slotsFor($this->unit, $this->campus, $this->manila('2026-10-21')), null, 'time');
        self::assertSame('Time has passed', $slots['10:00']['reason']);
        self::assertFalse($slots['11:00']['available']);
        self::assertTrue($slots['13:00']['available']);

        $early = $this->availability(taken: $taken, now: '2026-10-20 00:00:00');
        $slots = array_column($early->slotsFor($this->unit, $this->campus, $this->manila('2026-10-21')), null, 'time');
        self::assertSame('Already booked', $slots['10:00']['reason']);
        self::assertTrue($slots['09:00']['available']);
    }

    public function testProblemWithExplainsWhyATimeCannotBeBooked(): void
    {
        $taken = [new \DateTimeImmutable('2026-10-21 02:00:00', new \DateTimeZone('UTC'))];
        $availability = $this->availability(taken: $taken, now: '2026-10-20 00:00:00');
        $problem = fn (string $when): ?string => $availability->problemWith($this->unit, $this->campus, $this->manila($when));

        self::assertNull($problem('2026-10-21 09:00'));
        self::assertStringContainsString('already been booked', (string) $problem('2026-10-21 10:00'));
        self::assertStringContainsString('not open', (string) $problem('2026-10-21 12:00'), 'lunch break');
        self::assertStringContainsString('not open', (string) $problem('2026-10-21 09:30'), 'not on the slot grid');
        self::assertStringContainsString('not open', (string) $problem('2026-10-24 09:00'), 'Saturday');
        self::assertNull($problem('2026-10-19 09:00'), 'days before today are left to the date rule');
    }

    public function testATimeThatAlreadyPassedTodayIsRejected(): void
    {
        $availability = $this->availability(now: '2026-10-21 03:30:00');

        self::assertStringContainsString('already passed', (string) $availability->problemWith($this->unit, $this->campus, $this->manila('2026-10-21 09:00')));
        self::assertNull($availability->problemWith($this->unit, $this->campus, $this->manila('2026-10-21 14:00')));
    }

    /**
     * @param list<OfficeHour>         $hours
     * @param list<\DateTimeImmutable> $taken
     */
    private function availability(array $hours = [], array $taken = [], string $now = '2026-10-20 00:00:00'): AppointmentAvailability
    {
        $officeHours = $this->createStub(OfficeHourRepository::class);
        $officeHours->method('forUnit')->willReturn($hours);
        $appointments = $this->createStub(AppointmentRepository::class);
        $appointments->method('occupiedStarts')->willReturn($taken);
        $appointments->method('studentBusyAt')->willReturn(false);

        return new AppointmentAvailability($officeHours, $appointments, new MockClock($now, 'UTC'));
    }

    private function manila(string $when): \DateTimeImmutable
    {
        return new \DateTimeImmutable($when, new \DateTimeZone(self::MANILA));
    }
}
