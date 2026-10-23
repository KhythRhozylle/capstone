<?php

namespace App\Service;

use App\Entity\Campus;
use App\Entity\SasDirectorate;
use App\Entity\SasUnit;
use App\Entity\User;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class OfficialStaffAccounts
{
    public const PASSWORD = 'PASSWORD123!';

    // Official email, first name, surname, assigned role, compatibility role, unit, previous emails.
    public const ACCOUNTS = [
        ['vpsas@norsu.edu.ph', 'Cesar', 'Estrope', 'ROLE_VPSAS', null, null, ['vp.sas@norsu.edu.ph']],
        ['sds@norsu.edu.ph', 'Julio E.', 'Ventolero', 'ROLE_SDS_DIRECTOR', 'ROLE_DIRECTOR', null, []],
        ['sws@norsu.edu.ph', 'Annie Rose', 'Taguba', 'ROLE_SWS_DIRECTOR', 'ROLE_DIRECTOR', 'CARE', ['director@norsu.edu.ph']],
        ['isps@norsu.edu.ph', 'Carlou G.', 'Bernaldez', 'ROLE_ISPS_DIRECTOR', 'ROLE_DIRECTOR', null, []],
        ['studentgovernment@norsu.edu.ph', 'Roche', 'Cabanlit', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'GOV', []],
        ['disciplineorganizations@norsu.edu.ph', 'Apollo', 'Bugay', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'DISC', ['discipline@norsu.edu.ph']],
        ['studentpublication@norsu.edu.ph', 'Donald S.', 'Torres', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'PUB', []],
        ['internationalstudents@norsu.edu.ph', 'Stella Marie', 'Llera', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'INTL', []],
        ['care@norsu.edu.ph', 'Dulce', 'De Guzman', 'ROLE_UNIT_HEAD', 'ROLE_GUIDANCE', 'CARE', []],
        ['studententerprise@norsu.edu.ph', 'Pirl I.', 'Enquilino', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'ENT', []],
        ['sportsdevelopment@norsu.edu.ph', 'Orly V.', 'Ibero', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'SPORT', []],
        ['universityclinic@norsu.edu.ph', 'Michael', 'Oliveros', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'CLINIC', ['osas@norsu.edu.ph']],
        ['scholarships@norsu.edu.ph', 'Shiela May L.', 'Escorial', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'SCHOL', []],
        ['multifaith@norsu.edu.ph', 'Miriam', 'Eliaga', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'FAITH', []],
        ['housingresidential@norsu.edu.ph', 'Ma. Leroz P.', 'Rizada', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'HOUS', []],
        ['specialneeds@norsu.edu.ph', 'Efreneth G.', 'Alama', 'ROLE_UNIT_HEAD', 'ROLE_STAFF', 'SSN', []],
    ];

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    /** @return array<string, User> */
    public function synchronize(ObjectManager $manager, bool $resetPasswords = false): array
    {
        $campus = $manager->getRepository(Campus::class)->findOneBy(['code' => 'MAIN']);
        $units = [];
        foreach ($manager->getRepository(SasUnit::class)->findAll() as $unit) {
            $units[$unit->getCode()] = $unit;
        }
        if (!isset($units['CLINIC'])) {
            $directorate = $manager->getRepository(SasDirectorate::class)->findOneBy(['code' => 'ISPS']);
            if (null === $directorate) {
                throw new \RuntimeException('Load the campus and directorate fixtures before synchronizing staff accounts.');
            }
            $units['CLINIC'] = new SasUnit('CLINIC', 'University Clinic', $directorate);
            $manager->persist($units['CLINIC']);
        }

        $accounts = [];
        foreach (self::ACCOUNTS as [$email, $first, $last, $role, $compatibilityRole, $unitCode, $previousEmails]) {
            $user = $manager->getRepository(User::class)->findOneBy(['email' => $email]);
            foreach ($previousEmails as $previousEmail) {
                if (null === $user) {
                    $user = $manager->getRepository(User::class)->findOneBy(['email' => $previousEmail]);
                }
            }
            $new = null === $user;
            if ($new) {
                $user = new User();
                $user->setFirstName($first)->setLastName($last);
                $manager->persist($user);
            }
            $user->setEmail($email);
            $user->setRoles(null === $compatibilityRole ? [$role] : [$role, $compatibilityRole]);
            $user->setCampus($campus);
            $user->setStaffUnit(null === $unitCode ? null : ($units[$unitCode] ?? throw new \RuntimeException('Missing unit: '.$unitCode)));
            if ($new || $resetPasswords) {
                $user->setPassword($this->passwordHasher->hashPassword($user, self::PASSWORD));
            }
            $accounts[$email] = $user;
        }

        $manager->flush();

        return $accounts;
    }
}
