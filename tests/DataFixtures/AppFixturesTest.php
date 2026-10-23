<?php

namespace App\Tests\DataFixtures;

use App\DataFixtures\AppFixtures;
use App\Service\OfficialStaffAccounts;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Repository\DefaultRepositoryFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AppFixturesTest extends KernelTestCase
{
    public function testAppendingFixturesTwicePreservesExistingRecords(): void
    {
        self::bootKernel();
        $configuredManager = self::getContainer()->get(EntityManagerInterface::class);
        $configuration = clone $configuredManager->getConfiguration();
        $configuration->setRepositoryFactory(new DefaultRepositoryFactory());
        $manager = new EntityManager(
            DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]),
            $configuration,
        );
        $metadata = $manager->getMetadataFactory()->getAllMetadata();
        foreach ($metadata as $mapping) {
            $mapping->customRepositoryClassName = null;
        }
        (new SchemaTool($manager))->createSchema($metadata);
        $connection = $manager->getConnection();
        $connection->beginTransaction();

        try {
            $fixtures = self::getContainer()->get(AppFixtures::class);
            $fixtures->load($manager);
            $manager->clear();

            $classes = [Campus::class, SasDirectorate::class, SasUnit::class, User::class, StudentProfile::class, Appointment::class, ServiceRequest::class, Concern::class, CareCase::class, CareFollowUp::class, DocumentRequest::class, Announcement::class];
            self::assertNull($manager->getRepository(User::class)->findOneBy(['email' => 'student@norsu.edu.ph']));
            self::assertNull($manager->getRepository(User::class)->findOneBy(['email' => 'student.bais@norsu.edu.ph']));
            self::assertSame(0, $manager->getRepository(StudentProfile::class)->count([]));
            $counts = [];
            foreach ($classes as $class) {
                $counts[$class] = $manager->getRepository($class)->count([]);
            }

            $campus = $manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
            self::assertNotNull($campus);
            $campusName = $campus->getName();
            $student = $manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
            self::assertNotNull($student);
            $password = $student->getPassword();
            $student->setFirstName('Updated student');
            $manager->flush();
            $manager->clear();

            $fixtures->load($manager);
            $manager->clear();

            foreach ($counts as $class => $count) {
                self::assertSame($count, $manager->getRepository($class)->count([]), $class);
            }
            self::assertSame($campusName, $manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN'])->getName());
            $student = $manager->getRepository(User::class)->findOneBy(['email' => 'vpsas@norsu.edu.ph']);
            self::assertSame('Updated student', $student->getFirstName());
            self::assertSame($password, $student->getPassword());

            $legacy = $manager->getRepository(User::class)->findOneBy(['email' => 'sws@norsu.edu.ph']);
            $legacyId = $legacy->getId();
            $legacy->setEmail('director@norsu.edu.ph');
            $legacy->setPassword('old-password-hash');
            $manager->flush();
            $accounts = self::getContainer()->get(OfficialStaffAccounts::class)->synchronize($manager, true);
            self::assertCount(16, $accounts);
            foreach (['secretary@norsu.edu.ph', 'dianne@norsu.edu.ph', 'secretariat@norsu.edu.ph', 'officestaff@norsu.edu.ph'] as $removedEmail) {
                self::assertNull($manager->getRepository(User::class)->findOneBy(['email' => $removedEmail]));
            }
            self::assertSame($legacyId, $accounts['sws@norsu.edu.ph']->getId());
            self::assertNull($manager->getRepository(User::class)->findOneBy(['email' => 'director@norsu.edu.ph']));
            $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
            foreach (OfficialStaffAccounts::ACCOUNTS as [$email, , , $role]) {
                self::assertContains($role, $accounts[$email]->getRoles());
                self::assertTrue($hasher->isPasswordValid($accounts[$email], 'PASSWORD123!'), $email);
                self::assertFalse($hasher->isPasswordValid($accounts[$email], 'Password123!'), $email);
            }
            foreach ($counts as $class => $count) {
                self::assertSame($count, $manager->getRepository($class)->count([]), $class);
            }

        } finally {
            $connection->rollBack();
            $manager->clear();
        }
    }
}
