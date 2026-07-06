<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Services\WhatsAppNotificationService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(AppointmentConfirmed::class)]
#[ListensTo(AppointmentCancelled::class)]
class SendWhatsAppAppointmentNotification
{
    public function __construct(private readonly WhatsAppNotificationService $whatsApp) {}

    public function handle(AppointmentConfirmed|AppointmentCancelled $event): void
    {
        $appointment = $event->appointment;

        if (! $appointment->user_id || ! $appointment->staff_id) {
            return;
        }

        $appointment->loadMissing('user.preferences', 'staff');

        // Notifica il cliente solo quando è l'altra parte ad agire
        if ($event instanceof AppointmentCancelled && ! $event->byAdmin) {
            return;
        }

        if ($event instanceof AppointmentConfirmed && ! $event->byAdmin) {
            return;
        }

        $template = $event instanceof AppointmentConfirmed
            ? 'hello_world'
            : 'hello_world';

        $this->whatsApp->dispatchForAppointment(
            $appointment,
            $template,
            [],
        );
    }
}
