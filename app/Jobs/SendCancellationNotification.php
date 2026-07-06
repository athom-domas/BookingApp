<?php

namespace App\Jobs;

use App\Mail\AdminCancellationNotificationMail;
use App\Mail\AppointmentCancellationMail;
use App\Mail\StaffCancellationNotificationMail;
use App\Models\Appointment;
use App\Models\User;
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

    public function __construct(
        public readonly Appointment $appointment,
        public readonly bool $byAdmin = false,
    ) {}

    public function handle(NotificationService $notificationService): void
    {
        app()->instance('current_business_id', $this->appointment->business_id);

        $appointment = $this->appointment->load('user', 'staff.preferences', 'payment');

        $payment = $appointment->payment;

        if ($this->byAdmin) {
            // Admin ha cancellato: notifica il cliente
            Mail::send(new AppointmentCancellationMail($appointment, $appointment->user));
        } else {
            // Cliente ha cancellato: notifica gli admin
            $admins = User::role('admin')
                ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $this->appointment->business_id))
                ->get();
            foreach ($admins as $admin) {
                if ($admin->receive_email_notifications) {
                    Mail::send(new AdminCancellationNotificationMail($appointment, $admin, $payment));
                }
            }

            // Notifica lo staff assegnato se ha le notifiche email attive
            $staff = $appointment->staff;
            if ($staff && $staff->receive_email_notifications && ! $admins->contains('id', $staff->id)) {
                Mail::send(new StaffCancellationNotificationMail($appointment, $staff, $payment));
            }
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
