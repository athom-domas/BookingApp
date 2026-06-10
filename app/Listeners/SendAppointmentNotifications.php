<?php

namespace App\Listeners;

use App\Events\AppointmentConfirmed;
use App\Jobs\SendAppointmentConfirmation;
use App\Mail\AdminAppointmentNotificationMail;
use App\Mail\StaffAppointmentNotificationMail;
use App\Models\User;
use Illuminate\Events\Attributes\ListensTo;
use Illuminate\Support\Facades\Mail;

#[ListensTo(AppointmentConfirmed::class)]
class SendAppointmentNotifications
{
    public function handle(AppointmentConfirmed $event): void
    {
        SendAppointmentConfirmation::dispatch($event->appointment);

        $admins = User::role('admin')
            ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $event->appointment->business_id))
            ->get();

        foreach ($admins as $admin) {
            if ($admin->receive_email_notifications) {
                try {
                    Mail::to($admin->email)->send(new AdminAppointmentNotificationMail($event->appointment));
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('AdminAppointmentNotificationMail failed', [
                        'appointment_id' => $event->appointment->id,
                        'admin_id'       => $admin->id,
                        'error'          => $e->getMessage(),
                    ]);
                }
            }
        }

        $staff = $event->appointment->staff;
        if ($staff && $staff->receive_email_notifications) {
            try {
                Mail::to($staff->email)->send(new StaffAppointmentNotificationMail($event->appointment));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('StaffAppointmentNotificationMail failed', [
                    'appointment_id' => $event->appointment->id,
                    'staff_id'       => $staff->id,
                    'error'          => $e->getMessage(),
                ]);
            }
        }
    }
}
