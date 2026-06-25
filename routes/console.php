<?php

use App\Jobs\SendAppointmentReminder;
use App\Jobs\SendFollowUpReminder;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\FollowUpReminder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(function () {
    AppointmentReminder::pending()
        ->where('scheduled_for', '<=', now())
        ->each(fn (AppointmentReminder $reminder) => SendAppointmentReminder::dispatch($reminder));
})
    ->everyFiveMinutes()
    ->description('Dispatch due appointment reminders');

Schedule::call(function () {
    FollowUpReminder::pending()
        ->orderBy('id')
        ->chunkById(100, function ($reminders) {
            foreach ($reminders as $reminder) {
                SendFollowUpReminder::dispatch($reminder->id);
            }
        });
})->everyFiveMinutes()->description('Dispatch due follow-up reminders');

Schedule::call(function () {
    FollowUpReminder::stale()->update(['status' => 'pending', 'processing_at' => null]);
})->hourly()->description('Recover stale follow-up reminders');

Schedule::call(function () {
    Appointment::withoutGlobalScopes()
        ->pendingExpired()
        ->chunkById(50, function ($appointments) {
            foreach ($appointments as $appointment) {
                $appointment->update(['status' => 'cancelled']);
            }
        });
})->everyFifteenMinutes()->description('Expire unpaid pending appointments');
