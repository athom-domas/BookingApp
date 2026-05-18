<?php

namespace App\Jobs;

use App\Mail\AppointmentCancellationMail;
use App\Models\Appointment;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendCancellationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly Appointment $appointment) {}

    public function handle(NotificationService $notificationService): void
    {
        $appointment = $this->appointment->load('user', 'staff.preferences');

        Mail::send(new AppointmentCancellationMail($appointment, $appointment->user));

        Mail::send(new AppointmentCancellationMail($appointment, $appointment->staff));

        $staffPrefs = $appointment->staff->preferences;
        if ($staffPrefs?->receive_sms_reminders && $staffPrefs->phone_number) {
            $message = "Cancelled: {$appointment->services_label} on {$appointment->scheduled_date->format('d/m/Y H:i')}";
            $notificationService->sendSms($staffPrefs->phone_number, $message);
        }
    }

    public function failed(\Throwable $e): void
    {
        \Illuminate\Support\Facades\Log::error('SendCancellationNotification failed', [
            'appointment_id' => $this->appointment->id,
            'error'          => $e->getMessage(),
        ]);
    }
}
