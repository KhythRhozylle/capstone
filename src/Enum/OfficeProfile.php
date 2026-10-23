<?php

namespace App\Enum;

enum OfficeProfile: string
{
    case Cesar = 'cesar';
    case Dianne = 'dianne';
    case Mark = 'mark';

    public function label(): string
    {
        return match ($this) {
            self::Cesar => 'Cesar Estrope',
            self::Dianne => 'Dianne Futalan',
            self::Mark => 'Mark Raymond P. Baldado',
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::Cesar => 'Dr. Cesar P. Estrope',
            self::Dianne => 'Dianne Gutual-Futalan',
            self::Mark => 'Mark Raymond P. Baldado',
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Cesar => 'Vice President',
            self::Dianne => 'Secretary',
            self::Mark => 'Office Staff',
        };
    }

    public function initials(): string
    {
        return match ($this) {
            self::Cesar => 'CE', self::Dianne => 'DF', self::Mark => 'MB',
        };
    }
}
