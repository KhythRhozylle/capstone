<?php

namespace App\Enum;

enum AnnouncementScope: string
{
    case University = 'university';
    case Campus = 'campus';

    public function label(): string
    {
        return match ($this) {
            self::University => 'University-wide',
            self::Campus => 'Campus',
        };
    }
}
