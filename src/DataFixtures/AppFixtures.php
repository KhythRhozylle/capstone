<?php

namespace App\DataFixtures;

use App\Entity\Announcement;
use App\Entity\Appointment;
use App\Entity\Campus;
use App\Entity\CareCase;
use App\Entity\CareFollowUp;
use App\Entity\Concern;
use App\Entity\DocumentRequest;
use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use App\Entity\ServiceRequest;
use App\Entity\StudentProfile;
use App\Entity\User;
use App\Enum\AnnouncementScope;
use App\Enum\AppointmentMode;
use App\Enum\AppointmentStatus;
use App\Enum\CareCaseStatus;
use App\Enum\ConcernType;
use App\Enum\DocumentType;
use App\Enum\RequestStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
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
            $campus = new Campus($code, $name);
            $manager->persist($campus);
            $campuses[$code] = $campus;
        }

        $sds = new SasDirectorate('SDS', 'Student Development Services');
        $sws = new SasDirectorate('SWS', 'Student Welfare Services');
        $isps = new SasDirectorate('ISPS', 'Institutional Student Programs & Services');
        $manager->persist($sds);
        $manager->persist($sws);
        $manager->persist($isps);

        $units = [];
        $define = static function (string $code, string $name, SasDirectorate $dir, array $flags = []) use (&$units, $manager): SasUnit {
            $unit = new SasUnit($code, $name, $dir);
            $unit->setHandlesAppointments($flags['appointments'] ?? true);
            $unit->setHandlesCare($flags['care'] ?? false);
            $unit->setHandlesGrievance($flags['grievance'] ?? false);
            $unit->setHandlesDocuments($flags['documents'] ?? true);
            $manager->persist($unit);
            $units[$code] = $unit;

            return $unit;
        };

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

        $password = 'Password123!';
        $user = function (string $email, string $first, string $last, array $roles, ?Campus $campus = null, ?SasUnit $unit = null) use ($manager, $password): User {
            $u = new User();
            $u->setEmail($email);
            $u->setFirstName($first);
            $u->setLastName($last);
            $u->setRoles($roles);
            $u->setCampus($campus);
            $u->setStaffUnit($unit);
            $u->setPassword($this->passwordHasher->hashPassword($u, $password));
            $manager->persist($u);

            return $u;
        };

        $student = $user('student@norsu.edu.ph', 'Ana', 'Villanueva', ['ROLE_STUDENT'], $campuses['MAIN']);
        $profile = new StudentProfile();
        $profile->setUser($student);
        $profile->setStudentNumber('2024-00123');
        $profile->setProgram('BS Information Technology');
        $profile->setCollege('College of Engineering and Architecture');
        $profile->setYearLevel('3rd Year');
        $profile->setContactNumber('09171234567');
        $student->setStudentProfile($profile);
        $manager->persist($profile);

        $student2 = $user('student.bais@norsu.edu.ph', 'Carlo', 'Reyes', ['ROLE_STUDENT'], $campuses['BAIS1']);
        $profile2 = new StudentProfile();
        $profile2->setUser($student2);
        $profile2->setStudentNumber('2023-00881');
        $profile2->setProgram('BS Criminology');
        $profile2->setCollege('College of Criminal Justice');
        $profile2->setYearLevel('2nd Year');
        $profile2->setContactNumber('09179876543');
        $student2->setStudentProfile($profile2);
        $manager->persist($profile2);

        $osas = $user('osas@norsu.edu.ph', 'Michael', 'Oliveros', ['ROLE_STAFF'], $campuses['MAIN'], $units['OSAS']);
        $careStaff = $user('care@norsu.edu.ph', 'Dulce', 'De Guzman', ['ROLE_GUIDANCE'], $campuses['MAIN'], $units['CARE']);
        $disc = $user('discipline@norsu.edu.ph', 'Apollo', 'Bugay', ['ROLE_STAFF'], $campuses['MAIN'], $units['DISC']);
        $user('director@norsu.edu.ph', 'Annie Rose', 'Taguba', ['ROLE_DIRECTOR'], $campuses['MAIN'], $units['CARE']);
        $vpsas = $user('vpsas@norsu.edu.ph', 'Cesar', 'Estrope', ['ROLE_VPSAS']);
        $user('secretariat@norsu.edu.ph', 'Dianne', 'Gutual-Futalan', ['ROLE_VPSAS_SECRETARIAT'], $campuses['MAIN']);

        $apt = new Appointment();
        $apt->setStudent($student);
        $apt->setUnit($units['OSAS']);
        $apt->setCampus($campuses['MAIN']);
        $apt->setMode(AppointmentMode::Physical);
        $apt->setStatus(AppointmentStatus::Approved);
        $apt->setScheduledAt(new \DateTimeImmutable('+2 days 10:00'));
        $apt->setPurpose('Follow-up on student organization registration.');
        $apt->setReferenceCode('APT-2026-00001');
        $manager->persist($apt);

        $apt2 = new Appointment();
        $apt2->setStudent($student2);
        $apt2->setUnit($units['CARE']);
        $apt2->setCampus($campuses['BAIS1']);
        $apt2->setMode(AppointmentMode::Virtual);
        $apt2->setStatus(AppointmentStatus::Pending);
        $apt2->setScheduledAt(new \DateTimeImmutable('+3 days 14:00'));
        $apt2->setMeetingUrl('https://meet.norsu.edu.ph/care-demo');
        $apt2->setPurpose('Counseling appointment request.');
        $apt2->setReferenceCode('APT-2026-00002');
        $manager->persist($apt2);

        $svc = new ServiceRequest();
        $svc->setStudent($student);
        $svc->setUnit($units['SCHOL']);
        $svc->setCampus($campuses['MAIN']);
        $svc->setSubject('Scholarship requirement inquiry');
        $svc->setDescription('Need guidance on TES documentary requirements.');
        $svc->setStatus(RequestStatus::Processing);
        $svc->setReferenceCode('SVC-2026-00001');
        $manager->persist($svc);

        $concern = new Concern();
        $concern->setStudent($student);
        $concern->setCampus($campuses['MAIN']);
        $concern->setType(ConcernType::Concern);
        $concern->setSubject('Noise during evening review hours in dorm');
        $concern->setDescription('Housing area is too loud after 10 PM during exam week.');
        $concern->setAssignedUnit($units['HOUS']);
        $concern->setStatus(RequestStatus::InProgress);
        $concern->setReferenceCode('GRV-2026-00001');
        $manager->persist($concern);

        $complaint = new Concern();
        $complaint->setStudent($student2);
        $complaint->setCampus($campuses['BAIS1']);
        $complaint->setType(ConcernType::Complaint);
        $complaint->setSubject('Unfair organization election practice');
        $complaint->setDescription('Alleged campaigning inside classrooms during class hours.');
        $complaint->setRespondents('Campus student organization officers');
        $complaint->setAssignedUnit($units['DISC']);
        $complaint->setReferredToUnit($units['OSAS']);
        $complaint->setStatus(RequestStatus::Referred);
        $complaint->setReferenceCode('GRV-2026-00002');
        $manager->persist($complaint);

        $case = new CareCase();
        $case->setStudent($student);
        $case->setCampus($campuses['MAIN']);
        $case->setCounselor($careStaff);
        $case->setStatus(CareCaseStatus::FollowUp);
        $case->setReason('Requested counseling for academic stress.');
        $case->setStudentVisibleSummary('Case is active. Next follow-up is scheduled.');
        $case->setClinicalNotes('Internal CARE notes — not visible to the student.');
        $case->setReferenceCode('CARE-2026-00001');
        $follow = new CareFollowUp();
        $follow->setDueAt(new \DateTimeImmutable('+7 days'));
        $follow->setNotes('Check coping plan and class attendance.');
        $case->addFollowUp($follow);
        $manager->persist($case);

        $doc = new DocumentRequest();
        $doc->setStudent($student);
        $doc->setUnit($units['OSAS']);
        $doc->setCampus($campuses['MAIN']);
        $doc->setDocumentType(DocumentType::GoodMoral);
        $doc->setPurpose('Board exam application');
        $doc->setStatus(RequestStatus::Ready);
        $doc->setReferenceCode('DOC-2026-00001');
        $manager->persist($doc);

        $announcement = new Announcement();
        $announcement->setTitle('VPSAS student services portal is now online');
        $announcement->setBody('Students may now book appointments, file concerns, request SAS documents, and track requests in one place.');
        $announcement->setScope(AnnouncementScope::University);
        $announcement->setPublishedBy($vpsas);
        $manager->persist($announcement);

        $campusAnn = new Announcement();
        $campusAnn->setTitle('OSAS walk-in hours — Dumaguete Main');
        $campusAnn->setBody('Walk-in queue at University OSAS is open 8:00 AM to 4:00 PM, Monday to Friday.');
        $campusAnn->setScope(AnnouncementScope::Campus);
        $campusAnn->setCampus($campuses['MAIN']);
        $campusAnn->setPublishedBy($osas);
        $manager->persist($campusAnn);

        $manager->flush();
    }
}
