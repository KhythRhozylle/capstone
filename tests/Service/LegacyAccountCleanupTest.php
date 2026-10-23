<?php

namespace App\Tests\Service;

use App\DataFixtures\AppFixtures;
use App\Entity\Announcement;
use App\Entity\Campus;
use App\Entity\CareCase;
use App\Entity\CareFollowUp;
use App\Entity\ChatThreadPreference;
use App\Entity\Notification;
use App\Entity\OfficeMessage;
use App\Entity\StatusHistory;
use App\Entity\StudentProfile;
use App\Entity\User;
use App\Service\LegacyAccountCleanup;
use App\Service\OfficialStaffAccounts;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class LegacyAccountCleanupTest extends KernelTestCase
{
    public function testCleanupPreservesOfficialsAndRegisteredUsersAndBacksUpRemovedData(): void
    {
        $environment = [$_ENV['DATABASE_URL'] ?? null, $_SERVER['DATABASE_URL'] ?? null];
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = 'sqlite:///:memory:';
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($manager))->createSchema($manager->getMetadataFactory()->getAllMetadata());
        $backup = null;
        try {
            self::getContainer()->get(AppFixtures::class)->load($manager);
            $campus = $manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
            $users = [];
            foreach ([['student@norsu.edu.ph', 'Ana', 'Villanueva', 'ROLE_STUDENT'], ['student.bais@norsu.edu.ph', 'Carlo', 'Reyes', 'ROLE_STUDENT'], ['registered@example.test', 'Ana', 'Villanueva', 'ROLE_STUDENT'], ['vp.sas@norsu.edu.ph', 'Cesar', 'Estrope', 'ROLE_VPSAS']] as [$email, $first, $last, $role]) {
                $user = (new User())->setEmail($email)->setFirstName($first)->setLastName($last)->setRoles([$role])->setCampus($campus)->setPassword('preserve-this-password');
                $manager->persist($user);
                $users[] = $user;
            }
            [$ana, $carlo, $registered, $alias] = $users;
            $official = $manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
            $officialPassword = $official->getPassword();
            $profile = (new StudentProfile())->setUser($ana)->setStudentNumber('DEMO-ANA');
            $case = (new CareCase())->setStudent($ana)->setCampus($campus)->setReferenceCode('DEMO-CASE')->setReason('Demo case');
            $followUp = (new CareFollowUp())->setCareCase($case)->setDueAt(new \DateTimeImmutable('+1 day'));
            $announcement = (new Announcement())->setTitle('Preserve official announcement')->setBody('Existing content')->setPublishedBy($alias);
            $demoMessage = new OfficeMessage($registered, $ana, $registered->getFullName());
            $demoMessage->setBody('Demo recipient');
            $officialMessage = new OfficeMessage($alias, $registered, $alias->getFullName());
            $officialMessage->setBody('Preserve official message');
            $canonicalPreference = new ChatThreadPreference($official, $registered);
            $canonicalPreference->setArchived(true);
            foreach ([$profile, $case, $followUp, $announcement, $demoMessage, $officialMessage, $canonicalPreference, new ChatThreadPreference($alias, $registered), new Notification($ana, 'Demo notification', 'Remove this'), new Notification($alias, 'Official notification', 'Preserve this')] as $record) {
                $manager->persist($record);
            }
            $manager->flush();
            $manager->persist(new StatusHistory(CareCase::class, $case->getId(), null, 'requested', $ana));
            $manager->flush();
            $officialId = $official->getId();
            $registeredId = $registered->getId();
            $messageId = $officialMessage->getId();
            $announcementId = $announcement->getId();
            $cleanup = self::getContainer()->get(LegacyAccountCleanup::class);
            $plan = $cleanup->plan();
            self::assertCount(2, $plan['remove']);
            self::assertCount(1, $plan['merge']);
            self::assertSame(20, $manager->getRepository(User::class)->count([]));
            $backup = $cleanup->apply();
            self::assertFileExists($backup);
            $snapshot = unserialize(file_get_contents($backup), ['allowed_classes' => false]);
            self::assertCount(20, $snapshot['tables']['user']);
            $manager->clear();
            self::assertSame(17, $manager->getRepository(User::class)->count([]));
            foreach (['student@norsu.edu.ph', 'student.bais@norsu.edu.ph', 'vp.sas@norsu.edu.ph'] as $email) {
                self::assertNull($manager->getRepository(User::class)->findOneBy(['email' => $email]));
            }
            foreach (OfficialStaffAccounts::ACCOUNTS as [$email]) {
                self::assertNotNull($manager->getRepository(User::class)->findOneBy(['email' => $email]));
            }
            self::assertSame('preserve-this-password', $manager->find(User::class, $registeredId)->getPassword());
            self::assertSame($officialPassword, $manager->find(User::class, $officialId)->getPassword());
            self::assertSame($officialId, $manager->find(OfficeMessage::class, $messageId)->getSender()->getId());
            self::assertSame($officialId, $manager->find(Announcement::class, $announcementId)->getPublishedBy()->getId());
            self::assertSame(1, $manager->getRepository(OfficeMessage::class)->count([]));
            self::assertSame(0, $manager->getRepository(CareCase::class)->count([]));
            self::assertSame(0, $manager->getRepository(CareFollowUp::class)->count([]));
            self::assertSame(0, $manager->getRepository(StudentProfile::class)->count([]));
            self::assertSame(0, $manager->getRepository(StatusHistory::class)->count([]));
            self::assertSame(1, $manager->getRepository(ChatThreadPreference::class)->count([]));
            self::assertTrue($manager->getRepository(ChatThreadPreference::class)->findOneBy([])->isArchived());
            self::assertSame(1, $manager->getRepository(Notification::class)->count([]));
            self::assertNull($cleanup->apply());
        } finally {
            if (null !== $backup && is_file($backup)) {
                unlink($backup);
            }
            self::ensureKernelShutdown();
            [$env, $server] = $environment;
            if (null === $env) { unset($_ENV['DATABASE_URL']); } else { $_ENV['DATABASE_URL'] = $env; }
            if (null === $server) { unset($_SERVER['DATABASE_URL']); } else { $_SERVER['DATABASE_URL'] = $server; }
        }
    }
}
