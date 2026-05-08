<?php

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;

it('belongs to a customer user', function () {
    $customer = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);

    expect($appointment->user->id)->toBe($customer->id);
});

it('belongs to a staff user', function () {
    $staff = User::factory()->create();
    $appointment = Appointment::factory()->create(['staff_id' => $staff->id]);

    expect($appointment->staff->id)->toBe($staff->id);
});

it('belongs to a service', function () {
    $service = Service::factory()->create();
    $appointment = Appointment::factory()->create(['service_id' => $service->id]);

    expect($appointment->service->id)->toBe($service->id);
});

it('has many reminders', function () {
    $appointment = Appointment::factory()->create();
    AppointmentReminder::factory()->count(2)->create(['appointment_id' => $appointment->id]);

    expect($appointment->reminders)->toHaveCount(2);
});

it('has one payment', function () {
    $appointment = Appointment::factory()->create();
    Payment::factory()->create(['appointment_id' => $appointment->id]);

    expect($appointment->payment)->toBeInstanceOf(Payment::class);
});

it('scope upcoming returns future appointments', function () {
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5)]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5)]);

    expect(Appointment::upcoming()->count())->toBe(1);
});

it('scope pastAppointments returns past appointments', function () {
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5)]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5)]);

    expect(Appointment::pastAppointments()->count())->toBe(1);
});

it('scope confirmed returns only confirmed appointments', function () {
    Appointment::factory()->create(['status' => 'confirmed']);
    Appointment::factory()->create(['status' => 'pending']);

    expect(Appointment::confirmed()->count())->toBe(1);
});

it('isPast returns true for past appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->subDay()]);

    expect($appointment->isPast())->toBeTrue();
});

it('isUpcoming returns true for future appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->addDay()]);

    expect($appointment->isUpcoming())->toBeTrue();
});

it('canBeCancelled returns true for pending future appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDay(),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeTrue();
});

it('canBeCancelled returns false for completed appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDay(),
        'status' => 'completed',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});

it('canBeCancelled returns false for past appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->subDay(),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});
