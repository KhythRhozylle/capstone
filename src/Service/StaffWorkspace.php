<?php

namespace App\Service;

use App\Entity\SasUnit;
use App\Entity\User;
use App\Repository\SasUnitRepository;

class StaffWorkspace
{
    public const DIRECTORATES = ['ROLE_SDS_DIRECTOR' => 'SDS', 'ROLE_SWS_DIRECTOR' => 'SWS', 'ROLE_ISPS_DIRECTOR' => 'ISPS'];

    public function __construct(private readonly OfficeProfileContext $profiles, private readonly SasUnitRepository $units)
    {
    }

    public function supports(?User $user): bool
    {
        return $user instanceof User && ($this->profiles->supports($user) || in_array('ROLE_UNIT_HEAD', $user->getRoles(), true) || null !== self::directorateCode($user));
    }

    public static function directorateCode(User $user): ?string
    {
        foreach (self::DIRECTORATES as $role => $code) {
            if (in_array($role, $user->getRoles(), true)) {
                return $code;
            }
        }

        return null;
    }

    public function personalUnit(User $user): ?SasUnit
    {
        if ($this->profiles->supports($user)) {
            return $this->units->findOneBy(['code' => 'VPSAS']);
        }
        if ($code = self::directorateCode($user)) {
            return $this->units->findOneBy(['code' => $code.'_OFFICE']);
        }

        return in_array('ROLE_UNIT_HEAD', $user->getRoles(), true) ? $user->getStaffUnit() : null;
    }

    public function owns(User $user, SasUnit $unit): bool
    {
        return $this->supports($user) && $this->personalUnit($user)?->getId() === $unit->getId();
    }

    /** @return list<SasUnit>|null Null means university-wide access. */
    public function visibleUnits(User $user): ?array
    {
        if ($this->profiles->supports($user)) {
            return null;
        }
        if ($code = self::directorateCode($user)) {
            return $this->units->createQueryBuilder('u')->join('u.directorate', 'd')
                ->andWhere('d.code = :code')->andWhere('u.code != :vpsas')
                ->setParameter('code', $code)->setParameter('vpsas', 'VPSAS')->orderBy('u.name', 'ASC')->getQuery()->getResult();
        }

        return $user->getStaffUnit() ? [$user->getStaffUnit()] : [];
    }

    public function purpose(SasUnit $unit): string
    {
        return match ($unit->getCode()) {
            'GOV' => 'Student representation, leadership and student government support.',
            'DISC' => 'Student discipline and student organization concerns.',
            'CARE' => 'Counseling, assessments, student well-being and follow-ups.',
            'ENT' => 'Student entrepreneurship, enterprise development and mentoring.',
            'SPORT' => 'Student sports, athlete development and training programs.',
            'CLINIC' => 'Student health services, basic medical assistance and referrals.',
            'SCHOL' => 'Scholarships, grants and student financial assistance.',
            'FAITH' => 'Multi-faith programs and spiritual support.',
            'PUB' => 'Student journalism, publication and editorial guidance.',
            'INTL' => 'International student orientation, documentation and adjustment support.',
            'HOUS' => 'Food, student housing and residential support.',
            'SSN' => 'Accessibility, accommodations and support for students with special needs.',
            default => str_ends_with($unit->getCode(), '_OFFICE') ? 'Coordinate and supervise the units of '.$unit->getDirectorate()->getName().'.' : $unit->getName(),
        };
    }

    public function hasCareDesk(User $user): bool
    {
        return in_array('ROLE_GUIDANCE', $user->getRoles(), true) && (bool) $user->getStaffUnit()?->handlesCare();
    }
}
