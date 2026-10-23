<?php

namespace App\Enum;

/** The kinds of finished work a student can rate. */
enum FeedbackKind: string
{
    case Appointment = 'appointment';
    case Service = 'service';
    case Document = 'document';

    public function label(): string
    {
        return match ($this) {
            self::Appointment => 'appointment',
            self::Service => 'service request',
            self::Document => 'document request',
        };
    }
}
