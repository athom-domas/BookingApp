<?php

use App\Jobs\SendAppointmentReminder;
use App\Models\AppointmentReminder;
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
