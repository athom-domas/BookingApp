<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('POST /api/appointments/{id}/payment confirms payment and returns completed payment', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);
    $payment     = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $user->id,
        'stripe_transaction_id' => 'pi_confirmed_123',
        'status'                => 'completed',
        'amount'                => 60.00,
    ]);

    $this->mock(PaymentService::class)
        ->shouldReceive('confirmPayment')
        ->with($appointment->id)
        ->andReturn($payment->fresh());

    $response = $this->actingAs($user)->postJson("/api/appointments/{$appointment->id}/payment");

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.stripe_transaction_id', 'pi_confirmed_123');
});

it('POST /api/appointments/{id}/payment returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->postJson("/api/appointments/{$appointment->id}/payment");

    $response->assertForbidden();
});

it('POST /api/appointments/{id}/payment requires auth', function () {
    $appointment = Appointment::factory()->create();

    $this->postJson("/api/appointments/{$appointment->id}/payment")->assertUnauthorized();
});
