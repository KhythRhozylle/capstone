<?php

namespace App\Enum;

enum AppointmentMode: string
{
    case Physical = 'physical';
    case Virtual = 'virtual';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical',
            self::Virtual => 'Virtual',
        };
    }
}
