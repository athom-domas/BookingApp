<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('shows only the authenticated customer appointments', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $ownAppointment = Appointment::factory()->create([
        'user_id'     => $customer->id,
        'service_ids' => [Service::factory()->create(['name' => 'Servizio cliente'])->id],
    ]);
    $otherAppointment = Appointment::factory()->create([
        'service_ids' => [Service::factory()->create(['name' => 'Servizio altro cliente'])->id],
    ]);

    $response =     $this->actingAs($customer)->get('/portale/appuntamenti');

    $response->assertOk()
        ->assertSee($ownAppointment->services_label)
        ->assertDontSee($otherAppointment->services_label);
});

it('forbids viewing another customer appointment', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create();

    $this->actingAs($customer)
        ->get("/portale/appuntamenti/{$appointment->id}")
        ->assertForbidden();
});

it('shows the payment page for the owner', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);
    Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'user_id' => $customer->id,
        'status' => 'pending',
        'stripe_response' => ['client_secret' => 'pi_test_secret_123'],
    ]);
    config(['services.stripe.public' => 'pk_test_123']);

    $this->actingAs($customer)
        ->get("/portale/appuntamenti/{$appointment->id}/payment")
        ->assertOk()
        ->assertSee('Dati di pagamento');
});

it('forbids payment access for another customer appointment', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create();

    $this->actingAs($customer)
        ->get("/portale/appuntamenti/{$appointment->id}/payment")
        ->assertForbidden();
});

it('confirms a payment through the payment service', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);
    $payment = Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'user_id' => $customer->id,
        'status' => 'completed',
    ]);

    $this->mock(PaymentService::class)
        ->shouldReceive('confirmPayment')
        ->once()
        ->with($appointment->id)
        ->andReturn($payment);

    $this->actingAs($customer)
        ->post("/portale/appuntamenti/{$appointment->id}/payment/confirm")
        ->assertRedirect(route('portal.appointments.show', $appointment));
});

it('surfaces payment confirmation errors', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);

    $this->mock(PaymentService::class)
        ->shouldReceive('confirmPayment')
        ->andThrow(new BookingException('Pagamento non completato.'));

    $this->actingAs($customer)
        ->post("/portale/appuntamenti/{$appointment->id}/payment/confirm")
        ->assertSessionHasErrors('payment');
});

it('cancels an owned appointment through the appointment service', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('cancelAppointment')
        ->once()
        ->with($appointment->id, 'Cambio programma');

    $this->actingAs($customer)
        ->post("/portale/appuntamenti/{$appointment->id}/cancel", ['reason' => 'Cambio programma'])
        ->assertRedirect(route('portal.appointments.index'));
});
