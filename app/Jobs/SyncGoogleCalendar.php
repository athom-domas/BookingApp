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

        $business = \App\Models\Business::find($this->appointment->business_id);
        if (! $business?->canUseFeature('google_calendar')) {
            return;
        }

        if (! in_array($this->action, ['create', 'delete'])) {
            throw new \InvalidArgumentException("Unknown SyncGoogleCalendar action: {$this->action}");
        }

        $this->appointment->loadMissing('user');
        $refreshToken = $this->appointment->user->google_refresh_token ?? null;

        if ($this->action === 'create') {
            if (\App\Models\IntegrationSetting::getGoogleCalendarId()) {
                try {
                    $eventId = $calendarService->createEvent($this->appointment);
                    $this->appointment->update(['google_event_id' => $eventId]);
                } catch (\Throwable $e) {
                    Log::warning('SyncGoogleCalendar (business) failed', [
                        'appointment_id' => $this->appointment->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }

            if ($refreshToken) {
                try {
                    $customerEventId = $calendarService->createEventForUser($this->appointment, $refreshToken);
                    $this->appointment->update(['customer_google_event_id' => $customerEventId]);
                } catch (\Throwable $e) {
                    Log::warning('SyncGoogleCalendar (customer) failed', [
                        'appointment_id' => $this->appointment->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }

            return;
        }

        if ($this->appointment->google_event_id) {
            try {
                $calendarService->deleteEvent($this->appointment->google_event_id);
                $this->appointment->update(['google_event_id' => null]);
            } catch (\Throwable $e) {
                Log::warning('SyncGoogleCalendar (business) delete failed', [
                    'appointment_id' => $this->appointment->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }

        if ($refreshToken && $this->appointment->customer_google_event_id) {
            try {
                $calendarService->deleteEventForUser($this->appointment->customer_google_event_id, $refreshToken);
                $this->appointment->update(['customer_google_event_id' => null]);
            } catch (\Throwable $e) {
                Log::warning('SyncGoogleCalendar (customer) delete failed', [
                    'appointment_id' => $this->appointment->id,
                    'error'          => $e->getMessage(),
                ]);
            }
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
