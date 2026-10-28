<?php

namespace App\Enum;

enum DocumentRecordStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case ForReleasing = 'for_releasing';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::ForReleasing => 'For releasing',
            self::Released => 'Released',
        };
    }
}
