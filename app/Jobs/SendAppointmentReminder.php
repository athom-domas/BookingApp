<?php

namespace App\Jobs;

use App\Mail\AppointmentReminderMail;
use App\Models\AppointmentReminder;
use App\Services\NotificationService;
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

    public function handle(NotificationService $notificationService): void
    {
        if ($this->reminder->status === 'sent') {
            return;
        }

        $reminder    = $this->reminder->load('appointment.user.preferences', 'appointment.service', 'appointment.staff');
        $appointment = $reminder->appointment;
        $user        = $appointment->user;
        $prefs       = $user->preferences;

        Mail::to($user->email)->send(new AppointmentReminderMail($appointment));

        if ($prefs?->receive_sms_reminders && $prefs->phone_number) {
            $message = "Reminder: {$appointment->service->name} on {$appointment->scheduled_date->format('d/m/Y H:i')}";
            $notificationService->sendSms($prefs->phone_number, $message);
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
