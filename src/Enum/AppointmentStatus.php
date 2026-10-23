<?php

namespace App\Enum;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Rescheduled = 'rescheduled';
    case CheckedIn = 'checked_in';
    case Completed = 'completed';
    case NoShow = 'no_show';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::Rescheduled => 'Rescheduled',
            self::CheckedIn => 'Checked in',
            self::Completed => 'Completed',
            self::NoShow => 'No-show',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Statuses in which an appointment still holds its time slot.
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Pending, self::Approved, self::Rescheduled, self::CheckedIn];
    }
}
