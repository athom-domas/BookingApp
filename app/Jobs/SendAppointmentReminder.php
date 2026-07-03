<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Models\IntegrationSetting;
use App\Services\WhatsAppNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AppointmentReminder $reminder) {}

    public function handle(WhatsAppNotificationService $whatsApp): void
    {
        app()->instance('current_business_id', $this->reminder->business_id);

        if ($this->reminder->status === 'sent') {
            return;
        }

        $reminder    = $this->reminder->load('appointment.user.preferences', 'appointment.staff');
        $appointment = $reminder->appointment;

        $message = $whatsApp->dispatchForAppointment(
            $appointment,
            IntegrationSetting::getMetaWhatsAppTemplate(),
            WhatsAppNotificationService::appointmentParams($appointment),
        );

        if (! $message) {
            Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment));
        }

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
    }

    public function failed(\Throwable $e): void
    {
        $this->reminder->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
