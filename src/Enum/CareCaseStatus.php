<?php

namespace App\Enum;

enum CareCaseStatus: string
{
    case Requested = 'requested';
    case Accepted = 'accepted';
    case Active = 'active';
    case FollowUp = 'follow_up';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Accepted => 'Accepted',
            self::Active => 'Active',
            self::FollowUp => 'Follow-up',
            self::Closed => 'Closed',
        };
    }
}
