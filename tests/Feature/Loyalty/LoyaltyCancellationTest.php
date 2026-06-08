<?php

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AppointmentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    SystemSetting::current()->update([
        'loyalty_enabled'             => true,
        'loyalty_points_per_euro'     => 1,
        'cancellation_deadline_hours' => 24,
    ]);

    $this->customer = User::factory()->create()->assignRole('customer');
});

it('canBeCancelled() è true per un appuntamento pending', function () {
    $appointment = Appointment::factory()->create([
        'user_id' => $this->customer->id,
        'status'  => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeTrue();
});

it('canBeCancelled() è false per un appuntamento confirmed dentro la deadline', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addHours(12),
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});

it('canBeCancelled() è true per un appuntamento confirmed fuori dalla deadline', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(5),
    ]);

    expect($appointment->canBeCancelled())->toBeTrue();
});

it('canBeCancelled() è false per un appuntamento completato', function () {
    $appointment = Appointment::factory()->create([
        'user_id' => $this->customer->id,
        'status'  => 'completed',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});

it('cancella l appuntamento confermato fuori deadline e storna i punti', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'confirmed',
        'final_price'    => 70,
        'scheduled_date' => now()->addDays(10),
    ]);

    $account = LoyaltyAccount::where('user_id', $this->customer->id)->first();
    expect($account->points)->toBe(70);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    expect($appointment->fresh()->status)->toBe('cancelled')
        ->and($account->fresh()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $appointment->id)->where('type', 'reverse')->count())->toBe(1);
});

it('cancella il payment pending durante la cancellazione via service', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'pending',
        'final_price'    => 50,
        'scheduled_date' => now()->addDays(10),
    ]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $this->customer->id,
        'status'                => 'pending',
        'payment_method'        => 'cash',
        'stripe_transaction_id' => null,
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    expect($payment->fresh()->status)->toBe('cancelled');
});

it('lancia BookingException se l appuntamento non può essere cancellato', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addHours(6),
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment->id))
        ->toThrow(\App\Exceptions\BookingException::class);
});

it('non storna punti se non erano stati accreditati (final_price null)', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'pending',
        'final_price'    => null,
        'scheduled_date' => now()->addDays(10),
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('cancella con motivo personalizzato e salva nelle note', function () {
    $appointment = Appointment::factory()->create([
        'user_id'        => $this->customer->id,
        'status'         => 'pending',
        'scheduled_date' => now()->addDays(5),
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id, 'Cliente non disponibile');

    expect($appointment->fresh()->notes)->toBe('Cliente non disponibile');
});
