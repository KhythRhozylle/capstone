<?php

namespace App\Enum;

enum RequestStatus: string
{
    case Submitted = 'submitted';
    case Processing = 'processing';
    case Referred = 'referred';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Rejected = 'rejected';
    case Ready = 'ready';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Processing => 'Processing',
            self::Referred => 'Referred',
            self::InProgress => 'In progress',
            self::Resolved => 'Resolved',
            self::Closed => 'Closed',
            self::Rejected => 'Rejected',
            self::Ready => 'Ready for release',
            self::Released => 'Released',
        };
    }
}
