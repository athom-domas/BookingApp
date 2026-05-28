<?php

namespace App\Jobs;

use App\Models\Appointment;
use App\Services\GoogleCalendarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncGoogleCalendar implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $action,
    ) {}

    public function handle(GoogleCalendarService $calendarService): void
    {
        app()->instance('current_business_id', $this->appointment->business_id);

        if (! in_array($this->action, ['create', 'delete'])) {
            throw new \InvalidArgumentException("Unknown SyncGoogleCalendar action: {$this->action}");
        }

        try {
            if ($this->action === 'create') {
                $eventId = $calendarService->createEvent($this->appointment);
                $this->appointment->update(['google_event_id' => $eventId]);
                return;
            }

            if ($this->appointment->google_event_id) {
                $calendarService->deleteEvent($this->appointment->google_event_id);
                $this->appointment->update(['google_event_id' => null]);
            }
        } catch (\Throwable $e) {
            Log::warning('SyncGoogleCalendar failed — calendar sync skipped', [
                'appointment_id' => $this->appointment->id,
                'action'         => $this->action,
                'error'          => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncGoogleCalendar failed', [
            'appointment_id' => $this->appointment->id,
            'action'         => $this->action,
            'error'          => $exception->getMessage(),
        ]);
    }
}
