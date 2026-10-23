<?php

namespace App\Tests\Service;

use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use App\Entity\ServiceRequest;
use App\Entity\User;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Enum\RequestStatus;
use App\Service\DashboardStats;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PerformanceTest extends KernelTestCase
{
    private array $databaseEnvironment;
    private EntityManagerInterface $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->databaseEnvironment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        $this->manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($this->manager))->createSchema($this->manager->getMetadataFactory()->getAllMetadata());
        $directorate = new SasDirectorate('TEST', 'Test directorate');
        $unit = new SasUnit('TEST', 'Test unit', $directorate);
        $this->manager->persist($directorate);
        $this->manager->persist($unit);
        for ($i = 0; $i < 8; ++$i) {
            $campus = new Campus('TEST'.$i, 'Campus '.$i);
            $student = (new User())->setEmail('student'.$i.'@example.com')->setFirstName('Student')->setLastName((string) $i)->setPassword('unused')->setRoles(['ROLE_STUDENT']);
            $this->manager->persist($campus);
            $this->manager->persist($student);
            $appointment = (new Appointment())->setReferenceCode('A'.$i)->setStudent($student)->setCampus($campus)->setUnit($unit)->setStatus($i % 2 ? AppointmentStatus::Completed : AppointmentStatus::Pending)->setMode($i % 2 ? AppointmentMode::Virtual : AppointmentMode::Physical);
            $concern = (new Concern())->setReferenceCode('C'.$i)->setStudent($student)->setCampus($campus)->setAssignedUnit($unit)->setStatus(RequestStatus::Resolved)->setResolvedAt(new \DateTimeImmutable('2026-10-02 12:00:00'));
            (new \ReflectionProperty(Concern::class, 'createdAt'))->setValue($concern, new \DateTimeImmutable('2026-10-01 12:00:00'));
            $service = (new ServiceRequest())->setReferenceCode('S'.$i)->setStudent($student)->setCampus($campus)->setUnit($unit);
            $document = (new DocumentRequest())->setReferenceCode('D'.$i)->setStudent($student)->setCampus($campus)->setUnit($unit);
            foreach ([$appointment, $concern, $service, $document] as $record) {
                $this->manager->persist($record);
            }
        }
        $this->manager->flush();
        $this->manager->clear();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['_ENV', '_SERVER'] as $index => $global) {
            if (null === $this->databaseEnvironment[$index]) {
                unset($GLOBALS[$global]['DATABASE_URL']);
            } else {
                $GLOBALS[$global]['DATABASE_URL'] = $this->databaseEnvironment[$index];
            }
        }
    }

    public function testDashboardKeepsExactTotalsWithAFixedQueryBudget(): void
    {
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        $queries->reset();
        $stats = self::getContainer()->get(DashboardStats::class)->compile();
        $count = count($queries->getData()['default'] ?? []);
        self::assertGreaterThan(0, $count);
        self::assertLessThanOrEqual(24, $count, 'Dashboard query count must not grow with campuses or enum cases.');
        self::assertSame(8, $stats['appointments']);
        self::assertSame(8, $stats['concerns']);
        self::assertSame(8, $stats['documents']);
        self::assertSame(8, $stats['serviceRequests']);
        self::assertSame(['Physical' => 4, 'Virtual' => 4], $stats['appointmentsByMode']);
        self::assertSame(4, $stats['appointmentsByStatus']['Pending approval']);
        self::assertSame(4, $stats['appointmentsByStatus']['Completed']);
        self::assertSame(0, $stats['appointmentsByStatus']['Approved']);
        self::assertSame(['Resolved' => 8], $stats['concernsByStatus']);
        self::assertSame(24.0, $stats['avgResolutionHours']);
        self::assertCount(8, $stats['campusComparison']);
        foreach ($stats['campusComparison'] as $row) {
            self::assertSame([1, 1, 1, 1], [$row['appointments'], $row['concerns'], $row['services'], $row['documents']]);
        }
        // Counts are live after a write; no stale cross-user or statistics cache.
        $appointment = $this->manager->getRepository(Appointment::class)->findOneBy([]);
        $appointment->setStatus(AppointmentStatus::Approved);
        $this->manager->flush();
        self::assertSame(1, self::getContainer()->get(DashboardStats::class)->compile()['appointmentsByStatus']['Approved']);
    }

    public function testRenderingListRelationsDoesNotIssueQueriesPerRow(): void
    {
        $queries = self::getContainer()->get('doctrine.debug_data_holder');
        foreach ([Appointment::class, Concern::class, DocumentRequest::class, ServiceRequest::class] as $class) {
            $this->manager->clear();
            $rows = $this->manager->getRepository($class)->findForStaff(null, null);
            self::assertCount(8, $rows);
            $queries->reset();
            foreach ($rows as $row) {
                self::assertSame('Student', $row->getStudent()->getFirstName());
                self::assertStringStartsWith('Campus ', $row->getCampus()->getName());
                $unit = $row instanceof Concern ? $row->getAssignedUnit() : $row->getUnit();
                self::assertSame('Test unit', $unit->getName());
                self::assertNull($row->getStudent()->getStudentProfile());
            }
            self::assertSame([], $queries->getData(), $class.' must preload the relations used by table rows.');
        }
    }

    public function testRoleLookupDoesNotHydrateTheEntireStudentPopulation(): void
    {
        $staff = $this->manager->getRepository(User::class)->findOneBy(['email' => 'student0@example.com']);
        $staff->setRoles(['ROLE_STAFF']);
        $this->manager->flush();
        $this->manager->clear();
        $repository = $this->manager->getRepository(User::class);
        self::assertCount(1, $repository->findStaff());
        self::assertCount(1, $this->manager->getUnitOfWork()->getIdentityMap()[User::class]);
        self::assertCount(7, $repository->findByRole('ROLE_STUDENT'));
        self::assertCount(8, $repository->findByRole('ROLE_USER'), 'ROLE_USER is implicit for every account.');
        self::assertSame([], $repository->findByRole('ROLE_STAFF_EXTRA'));
    }

}
