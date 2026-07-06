<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SendAppointmentConfirmation;
use App\Jobs\SendCancellationNotification;
use App\Mail\AdminAppointmentNotificationMail;
use App\Mail\StaffAppointmentNotificationMail;
use App\Models\User;
use Illuminate\Events\Attributes\ListensTo;
use Illuminate\Support\Facades\Mail;

#[ListensTo(AppointmentConfirmed::class)]
#[ListensTo(AppointmentCancelled::class)]
class SendAppointmentNotifications
{
    public function handle(AppointmentConfirmed|AppointmentCancelled $event): void
    {
        $appointment = $event->appointment;
        $appointment->loadMissing('user.preferences');
        $channel = $appointment->user?->preferences?->notification_channel ?? 'email';

        if ($event instanceof AppointmentConfirmed) {
            if ($event->byAdmin) {
                // Admin ha creato/confermato: notifica il cliente
                if ($channel !== 'whatsapp') {
                    SendAppointmentConfirmation::dispatch($appointment);
                }
            } else {
                // Cliente ha prenotato: notifica admin e staff
                $this->notifyAdminsAndStaff($appointment);
            }
        }

        if ($event instanceof AppointmentCancelled) {
            if ($event->byAdmin) {
                // Admin ha cancellato: notifica cliente solo se canale email (WhatsApp lo gestisce l'altro listener)
                if ($channel !== 'whatsapp') {
                    SendCancellationNotification::dispatch($appointment, byAdmin: true);
                }
            } else {
                // Cliente ha cancellato: notifica admin via email, sempre
                SendCancellationNotification::dispatch($appointment, byAdmin: false);
            }
        }
    }

    private function notifyAdminsAndStaff(\App\Models\Appointment $appointment): void
    {
        $admins = User::role('admin')
            ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $appointment->business_id))
            ->get();

        foreach ($admins as $admin) {
            if ($admin->receive_email_notifications) {
                try {
                    Mail::to($admin->email)->send(new AdminAppointmentNotificationMail($appointment));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('AdminAppointmentNotificationMail failed', [
                        'appointment_id' => $appointment->id,
                        'admin_id'       => $admin->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        $staff = $appointment->staff;
        if ($staff && $staff->receive_email_notifications) {
            try {
                Mail::to($staff->email)->send(new StaffAppointmentNotificationMail($appointment));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('StaffAppointmentNotificationMail failed', [
                    'appointment_id' => $appointment->id,
                    'staff_id'       => $staff->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
