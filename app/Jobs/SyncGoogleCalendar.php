<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $action,
    ) {}

    public function handle(GoogleCalendarService $calendarService): void
    {
        if ($this->action === 'create') {
            $eventId = $calendarService->createEvent($this->appointment);
            $this->appointment->update(['google_event_id' => $eventId]);
            return;
        }

        if ($this->action === 'delete' && $this->appointment->google_event_id) {
            $calendarService->deleteEvent($this->appointment->google_event_id);
            $this->appointment->update(['google_event_id' => null]);
        }
    }
}
