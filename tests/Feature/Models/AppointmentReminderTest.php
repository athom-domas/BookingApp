<?php

use App\Models\Appointment;
use App\Models\AppointmentReminder;

it('belongs to an appointment', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create(['appointment_id' => $appointment->id]);

    expect($reminder->appointment->id)->toBe($appointment->id);
});

it('scope pending returns only pending reminders', function () {
    AppointmentReminder::factory()->create(['status' => 'pending']);
    AppointmentReminder::factory()->create(['status' => 'sent']);
    AppointmentReminder::factory()->create(['status' => 'failed']);

    expect(AppointmentReminder::pending()->count())->toBe(1);
});
