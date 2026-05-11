<?php

use App\Mail\AppointmentCancellationMail;
use App\Mail\AppointmentConfirmationMail;
use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\User;

it('AppointmentReminderMail renders correctly', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');

    $mailable = new AppointmentReminderMail($appointment);

    $mailable->assertTo($appointment->user->email);
    expect($mailable->render())->toContain($appointment->service->name);
});

it('AppointmentConfirmationMail renders correctly', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');

    $mailable = new AppointmentConfirmationMail($appointment);

    $mailable->assertTo($appointment->user->email);
    expect($mailable->render())->toContain($appointment->service->name);
});

it('AppointmentCancellationMail renders correctly for customer', function () {
    $appointment = Appointment::factory()->create();
    $appointment->load('user', 'service', 'staff');
    $recipient = $appointment->user;

    $mailable = new AppointmentCancellationMail($appointment, $recipient);

    $mailable->assertTo($recipient->email);
    expect($mailable->render())->toContain($appointment->service->name);
});
