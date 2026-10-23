<?php

namespace App\Tests\Service;

use App\Service\StudentServiceCatalog;
use PHPUnit\Framework\TestCase;

class StudentServiceCatalogOfficialsTest extends TestCase
{
    private StudentServiceCatalog $catalog;

    protected function setUp(): void
    {
        $this->catalog = new StudentServiceCatalog(\dirname(__DIR__, 2));
    }

    public function testOfficialsFollowTheOrganizationalChart(): void
    {
        $officials = $this->catalog->officials();

        self::assertSame('VPSAS', $officials['vicePresident']['unitCode']);
        self::assertSame(['SDS', 'SWS', 'ISPS'], array_column($officials['directorates'], 'code'));
        self::assertSame(
            ['SDS_OFFICE', 'SWS_OFFICE', 'ISPS_OFFICE'],
            array_map(static fn (array $directorate): string => $directorate['director']['unitCode'], $officials['directorates']),
        );
        self::assertSame([4, 2, 6], array_map(static fn (array $directorate): int => \count($directorate['heads']), $officials['directorates']));
    }

    public function testEveryOfficialHasADistinctUnitAndAPhoto(): void
    {
        $officials = $this->catalog->officials();
        $people = [$officials['vicePresident']];
        foreach ($officials['directorates'] as $directorate) {
            $people = [...$people, $directorate['director'], ...$directorate['heads']];
        }

        self::assertCount(16, $people);
        self::assertCount(16, array_unique(array_column($people, 'unitCode')));
        foreach ($people as $person) {
            self::assertFileExists(\dirname(__DIR__, 2).'/public/'.$person['image']);
        }
    }

    public function testOfficialForUnitFindsTheRightPerson(): void
    {
        self::assertSame('Dr. Michael Oliveros', $this->catalog->officialForUnit('CLINIC')['name']);
        self::assertSame('Dr. Cesar P. Estrope', $this->catalog->officialForUnit('VPSAS')['name']);
        self::assertNull($this->catalog->officialForUnit('UNKNOWN'));
    }
}
