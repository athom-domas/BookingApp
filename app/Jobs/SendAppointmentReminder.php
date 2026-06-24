<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\IntegrationSetting;
use App\Services\WhatsAppService;
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

    public function handle(WhatsAppService $whatsApp): void
    {
        app()->instance('current_business_id', $this->reminder->business_id);

        if ($this->reminder->status === 'sent') {
            return;
        }

        $reminder    = $this->reminder->load('appointment.user.preferences', 'appointment.staff');
        $appointment = $reminder->appointment;
        $prefs       = $appointment->user->preferences;

        $channel = $prefs?->notification_channel ?? 'email';

        $sentViaWhatsApp = false;

        if ($channel === 'whatsapp' && $prefs?->phone_number && IntegrationSetting::hasMetaWhatsApp()) {
            $sentViaWhatsApp = $whatsApp->sendTemplateDefault($prefs->phone_number, [
                $appointment->user->name,
                $appointment->services_label,
                $appointment->scheduled_date->format('d/m/Y'),
                $appointment->scheduled_date->format('H:i'),
                $appointment->staff->name,
            ]);
        }

        if (! $sentViaWhatsApp) {
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
