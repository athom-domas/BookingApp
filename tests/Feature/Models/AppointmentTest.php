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

it('returns services via accessor', function () {
    $service = Service::factory()->create();
    $appointment = Appointment::factory()->create(['service_ids' => [$service->id]]);

    expect($appointment->services->first()->id)->toBe($service->id);
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
    $user = User::factory()->create();
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5), 'user_id' => $user->id]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5), 'user_id' => $user->id]);

    expect(Appointment::where('user_id', $user->id)->upcoming()->count())->toBe(1);
});

it('scope pastAppointments returns past appointments', function () {
    $user = User::factory()->create();
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5), 'user_id' => $user->id]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5), 'user_id' => $user->id]);

    expect(Appointment::where('user_id', $user->id)->pastAppointments()->count())->toBe(1);
});

it('scope confirmed returns only confirmed appointments', function () {
    $user = User::factory()->create();
    Appointment::factory()->create(['status' => 'confirmed', 'user_id' => $user->id]);
    Appointment::factory()->create(['status' => 'pending', 'user_id' => $user->id]);

    expect(Appointment::where('user_id', $user->id)->confirmed()->count())->toBe(1);
});

it('isPast returns true for past appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->subDay()]);

    expect($appointment->isPast())->toBeTrue();
});

it('isUpcoming returns true for future appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->addDay()]);

    expect($appointment->isUpcoming())->toBeTrue();
});

it('canBeCancelled returns true for pending appointments more than 24h away', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(2),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeTrue();
});

it('canBeCancelled returns false for completed appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(2),
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

it('canBeCancelled returns false when less than 24 hours away', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addHours(12),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});

it('pendingExpired scope excludes recent pending appointments', function () {
    $recent = Appointment::factory()->create(['status' => 'pending', 'created_at' => now()->subMinutes(10)]);
    $old    = Appointment::factory()->create(['status' => 'pending', 'created_at' => now()->subMinutes(45)]);

    $ids = Appointment::pendingExpired()->pluck('id');

    expect($ids)->not->toContain($recent->id)
        ->and($ids)->toContain($old->id);
});

it('pendingExpired scope excludes confirmed appointments', function () {
    Appointment::factory()->create(['status' => 'confirmed', 'created_at' => now()->subHour()]);

    expect(Appointment::pendingExpired()->count())->toBe(0);
});

it('cancelling a pending appointment also cancels its pending payment', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => null,
    ]);

    $appointment->update(['status' => 'cancelled']);

    expect($payment->fresh()->status)->toBe('cancelled');
});
