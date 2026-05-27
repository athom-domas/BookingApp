<?php

namespace App\Services;

use App\Models\Appointment;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class GoogleCalendarService
{
    public function __construct(private readonly Calendar $calendar) {}

    public function createEvent(Appointment $appointment): string
    {
        $appointment->load('user', 'staff');
        $services = $appointment->services;

        $start = new EventDateTime();
        $start->setDateTime($appointment->scheduled_date->toRfc3339String());
        $start->setTimeZone('UTC');

        $end = new EventDateTime();
        $end->setDateTime(
            $appointment->scheduled_date->clone()
                ->addMinutes($services->sum('duration_minutes'))
                ->toRfc3339String()
        );
        $end->setTimeZone('UTC');

        $event = new Event([
            'summary'     => $services->pluck('name')->implode(', ') . ' - ' . $appointment->user->name,
            'description' => $appointment->notes ?? '',
            'start'       => $start,
            'end'         => $end,
        ]);

        $created = $this->calendar->events->insert(
            \App\Models\IntegrationSetting::getGoogleCalendarId() ?? config('services.google.calendar_id'),
            $event
        );

        return $created->getId();
    }

    public function deleteEvent(string $eventId): void
    {
        $this->calendar->events->delete(
            \App\Models\IntegrationSetting::getGoogleCalendarId() ?? config('services.google.calendar_id'),
            $eventId
        );
    }
}
