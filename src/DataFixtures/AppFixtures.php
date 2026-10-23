<?php

namespace App\DataFixtures;

use App\Entity\Announcement;
use App\Entity\Campus;
use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use App\Entity\User;
use App\Enum\AnnouncementScope;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use App\Service\OfficialStaffAccounts;

class AppFixtures extends Fixture
{
    public function __construct(private readonly OfficialStaffAccounts $officialStaffAccounts)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $campuses = [];
        foreach ([
            ['MAIN', 'Dumaguete Main Campus'],
            ['BAIS1', 'Bais Campus I'],
            ['BAIS2', 'Bais Campus II'],
            ['BSC', 'Bayawan–Sta. Catalina Campus'],
            ['GUI', 'Guihulngan Campus'],
            ['MAB', 'Mabinay Campus'],
            ['PAM', 'Pamplona Campus'],
            ['SIA', 'Siaton Campus'],
        ] as [$code, $name]) {
            $campus = $manager->getRepository(Campus::class)->findOneBy(['code' => $code]);
            if (null === $campus) {
                $campus = new Campus($code, $name);
                $manager->persist($campus);
            }
            $campuses[$code] = $campus;
        }

        $directorate = static function (string $code, string $name) use ($manager): SasDirectorate {
            $existing = $manager->getRepository(SasDirectorate::class)->findOneBy(['code' => $code]);
            if (null !== $existing) {
                return $existing;
            }

            $directorate = new SasDirectorate($code, $name);
            $manager->persist($directorate);

            return $directorate;
        };
        $sds = $directorate('SDS', 'Student Development Services');
        $sws = $directorate('SWS', 'Student Welfare Services');
        $isps = $directorate('ISPS', 'Institutional Student Programs & Services');

        $units = [];
        $define = static function (string $code, string $name, SasDirectorate $dir, array $flags = []) use (&$units, $manager): SasUnit {
            $existing = $manager->getRepository(SasUnit::class)->findOneBy(['code' => $code]);
            if (null !== $existing) {
                $units[$code] = $existing;

                return $existing;
            }

            $unit = new SasUnit($code, $name, $dir);
            $unit->setHandlesAppointments($flags['appointments'] ?? true);
            $unit->setHandlesCare($flags['care'] ?? false);
            $unit->setHandlesGrievance($flags['grievance'] ?? false);
            $unit->setHandlesDocuments($flags['documents'] ?? true);
            $manager->persist($unit);
            $units[$code] = $unit;

            return $unit;
        };

        $define('SDS_OFFICE', 'Office of the SDS Director', $sds, ['documents' => false]);
        $define('SWS_OFFICE', 'Office of the SWS Director', $sws, ['documents' => false]);
        $define('ISPS_OFFICE', 'Office of the ISPS Director', $isps, ['documents' => false]);
        $define('VPSAS', 'Office of the VPSAS', $sds, ['documents' => false]);
        $define('OSAS', 'University OSAS', $sds);
        $define('GOV', 'Student Government', $sds);
        $define('DISC', 'Board of Discipline & Campus Student Organization', $sds, ['grievance' => true]);
        $define('SPORT', 'Sports Development Program', $sds);
        $define('CARE', 'CARE — Counselling, Assessment, Resources & Enhancement', $sws, ['care' => true, 'documents' => false]);
        $define('ENT', 'Student Enterprise Development', $sws);
        $define('SCHOL', 'Scholarship and Financial Assistance', $isps);
        $define('FAITH', 'Multi-Faith Services', $isps);
        $define('PUB', 'Student Publication', $isps);
        $define('INTL', 'Foreign / International Student Services', $isps);
        $define('HOUS', 'Food, Student Housing & Residential', $isps);
        $define('SSN', 'Services for Students with Special Needs', $isps);

        $manager->flush();
        $accounts = $this->officialStaffAccounts->synchronize($manager);
        $vpsas = $accounts['vpsas@norsu.edu.ph'];

        if (null === $manager->getRepository(Announcement::class)->findOneBy(['title' => 'VPSAS student services portal is now online', 'scope' => AnnouncementScope::University])) {
            $announcement = new Announcement();
            $announcement->setTitle('VPSAS student services portal is now online');
            $announcement->setBody('Students may now book appointments, file concerns, request SAS documents, and track requests in one place.');
            $announcement->setScope(AnnouncementScope::University);
            $announcement->setPublishedBy($vpsas);
            $manager->persist($announcement);
        }

        if (null === $manager->getRepository(Announcement::class)->findOneBy(['title' => 'OSAS walk-in hours — Dumaguete Main', 'scope' => AnnouncementScope::Campus, 'campus' => $campuses['MAIN']])) {
            $campusAnn = new Announcement();
            $campusAnn->setTitle('OSAS walk-in hours — Dumaguete Main');
            $campusAnn->setBody('Walk-in queue at University OSAS is open 8:00 AM to 4:00 PM, Monday to Friday.');
            $campusAnn->setScope(AnnouncementScope::Campus);
            $campusAnn->setCampus($campuses['MAIN']);
            $campusAnn->setPublishedBy($vpsas);
            $manager->persist($campusAnn);
        }

        $manager->flush();
    }
}
