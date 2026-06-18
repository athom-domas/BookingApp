<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;

it('belongs to an appointment', function () {
    $appointment = Appointment::factory()->create();
    $payment = Payment::factory()->create(['appointment_id' => $appointment->id]);

    expect($payment->appointment->id)->toBe($appointment->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    expect($payment->user->id)->toBe($user->id);
});

it('scope completed returns only completed payments', function () {
    $completed = Payment::factory()->create(['status' => 'completed']);
    $pending   = Payment::factory()->create(['status' => 'pending']);
    $failed    = Payment::factory()->create(['status' => 'failed']);
    $ids = [$completed->id, $pending->id, $failed->id];

    expect(Payment::whereIn('id', $ids)->completed()->count())->toBe(1);
});
