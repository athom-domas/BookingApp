<?php

namespace App\Listeners;

use App\Events\AppointmentConfirmed;
use App\Jobs\SendAppointmentConfirmation;
use App\Mail\AdminAppointmentNotificationMail;
use App\Models\User;
use Illuminate\Events\Attributes\ListensTo;
use Illuminate\Support\Facades\Mail;

#[ListensTo(AppointmentConfirmed::class)]
class SendAppointmentNotifications
{
    public function handle(AppointmentConfirmed $event): void
    {
        SendAppointmentConfirmation::dispatch($event->appointment);

        $admins = User::role('admin')->get();

        foreach ($admins as $admin) {
            Mail::to($admin->email)->send(new AdminAppointmentNotificationMail($event->appointment));
        }
    }
}
