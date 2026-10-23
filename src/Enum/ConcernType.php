<?php

namespace App\Enum;

enum ConcernType: string
{
    case Concern = 'concern';
    case Complaint = 'complaint';

    public function label(): string
    {
        return match ($this) {
            self::Concern => 'Concern',
            self::Complaint => 'Complaint',
        };
    }
}
