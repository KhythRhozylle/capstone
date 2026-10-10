<?php

namespace App\Enum;

enum QueueStatus: string
{
    case Waiting = 'waiting';
    case Called = 'called';
    case Served = 'served';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting',
            self::Called => 'Now serving',
            self::Served => 'Served',
            self::Skipped => 'Skipped',
        };
    }
}
