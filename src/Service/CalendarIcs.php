<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Enum\AppointmentMode;
use Symfony\Component\HttpFoundation\Response;

class CalendarIcs
{
    public function appointmentResponse(Appointment $appointment): Response
    {
        $filename = $appointment->getReferenceCode().'.ics';

        return new Response($this->render($appointment), Response::HTTP_OK, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function render(Appointment $appointment): string
    {
        $start = $appointment->getScheduledAt();
        $end = $start->modify('+30 minutes');
        $uid = $appointment->getReferenceCode().'@norsu-sas.local';
        $summary = $this->escape('NORSU SAS — '.$appointment->getUnit()->getName());
        $description = $this->escape($appointment->getPurpose());
        $location = $appointment->getMode() === AppointmentMode::Virtual
            ? $this->escape((string) ($appointment->getMeetingUrl() ?: 'Virtual appointment'))
            : $this->escape($appointment->getCampus()->getName().' — '.$appointment->getUnit()->getName());

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//NORSU-SAS Insight//Appointments//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$this->utc($appointment->getCreatedAt()),
            'DTSTART:'.$this->utc($start),
            'DTEND:'.$this->utc($end),
            'SUMMARY:'.$summary,
            'DESCRIPTION:'.$description,
            'LOCATION:'.$location,
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines)."\r\n";
    }

    private function utc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function escape(string $value): string
    {
        return str_replace(["\\", ',', ';', "\r\n", "\n"], ['\\\\', '\\,', '\\;', '\\n', '\\n'], $value);
    }
}
