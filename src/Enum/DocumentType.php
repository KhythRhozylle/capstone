<?php

namespace App\Enum;

enum DocumentType: string
{
    case GoodMoral = 'good_moral';
    case CertificateOfEnrollment = 'certificate_of_enrollment';
    case Certification = 'sas_certification';
    case ReferralLetter = 'referral_letter';
    case Clearance = 'sas_clearance';

    public function label(): string
    {
        return match ($this) {
            self::GoodMoral => 'Certificate of Good Moral Character',
            self::CertificateOfEnrollment => 'Certificate of Enrollment (SAS)',
            self::Certification => 'SAS Certification',
            self::ReferralLetter => 'Referral letter',
            self::Clearance => 'SAS clearance',
        };
    }
}
