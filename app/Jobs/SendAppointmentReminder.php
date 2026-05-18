<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class SendAppointmentReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly AppointmentReminder $reminder) {}

    public function handle(NotificationService $notificationService): void
    {
        if ($this->reminder->status === 'sent') {
            return;
        }

        $reminder    = $this->reminder->load('appointment.user.preferences', 'appointment.staff');
        $appointment = $reminder->appointment;
        $user        = $appointment->user;
        $prefs       = $user->preferences;

        $channel = $prefs?->notification_channel ?? 'email';

        match ($channel) {
            'sms'      => $prefs->phone_number
                ? $this->sendSms($appointment, $prefs->phone_number, $notificationService)
                : Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment)),
            'whatsapp' => $prefs->phone_number
                ? $this->sendWhatsApp($appointment, $prefs->phone_number, $notificationService)
                : Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment)),
            default    => Mail::to($appointment->user->email)->send(new AppointmentReminderMail($appointment)),
        };

        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
    }

    private function sendSms(Appointment $appointment, string $phone, NotificationService $notificationService): void
    {
        $text = "Ciao {$appointment->user->name}, appuntamento {$appointment->services_label} il {$appointment->scheduled_date->format('d/m/Y')} alle {$appointment->scheduled_date->format('H:i')} con {$appointment->staff->name}. Conferma: " .
            URL::signedRoute('appointment.public.confirm', ['appointment' => $appointment], now()->addHours(48)) .
            " | Disdici: " .
            URL::signedRoute('appointment.public.cancel', ['appointment' => $appointment], now()->addHours(48));
        $notificationService->sendSms($phone, $text);
    }

    private function sendWhatsApp(Appointment $appointment, string $phone, NotificationService $notificationService): void
    {
        $text = "Ciao {$appointment->user->name}, appuntamento {$appointment->services_label} il {$appointment->scheduled_date->format('d/m/Y')} alle {$appointment->scheduled_date->format('H:i')} con {$appointment->staff->name}. Conferma: " .
            URL::signedRoute('appointment.public.confirm', ['appointment' => $appointment], now()->addHours(48)) .
            " | Disdici: " .
            URL::signedRoute('appointment.public.cancel', ['appointment' => $appointment], now()->addHours(48));
        $notificationService->sendWhatsApp($phone, $text);
    }

    public function failed(\Throwable $e): void
    {
        $this->reminder->update([
            'status'        => 'failed',
            'error_message' => $e->getMessage(),
        ]);
    }
}
