<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('POST /api/appointments books appointment and returns payment_intent_id', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['price' => 60.00, 'duration_minutes' => 30]);

    $appointment = Appointment::factory()->create([
        'user_id'     => $user->id,
        'service_ids' => [$service->id],
        'staff_id'    => $staff->id,
        'final_price' => 60.00,
    ]);

    $payment = Payment::factory()->create([
        'appointment_id'        => $appointment->id,
        'user_id'               => $user->id,
        'stripe_transaction_id' => 'pi_test_book_123',
        'status'                => 'pending',
        'amount'                => 60.00,
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('bookAppointment')
        ->with($user->id, [$service->id], $staff->id, Mockery::on(fn ($d) => $d instanceof Carbon))
        ->andReturn($appointment);

    $this->mock(PaymentService::class)
        ->shouldReceive('initiateStripePayment')
        ->with($appointment->id, 6000)
        ->andReturn($payment);

    $response = $this->actingAs($user)->postJson('/api/appointments', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(5)->toDateTimeString(),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.payment_intent_id', 'pi_test_book_123')
        ->assertJsonPath('data.appointment.id', $appointment->id);
});

it('POST /api/appointments returns 422 on BookingException', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create();

    $this->mock(AppointmentService::class)
        ->shouldReceive('bookAppointment')
        ->andThrow(new BookingException('Staff non disponibile.'));

    $response = $this->actingAs($user)->postJson('/api/appointments', [
        'service_ids'    => [$service->id],
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(5)->toDateTimeString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Staff non disponibile.');
});

it('POST /api/appointments validates required fields', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $response = $this->actingAs($user)->postJson('/api/appointments', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['service_ids', 'staff_id', 'scheduled_date']);
});

it('POST /api/appointments requires auth', function () {
    $this->postJson('/api/appointments', [])->assertUnauthorized();
});

it('GET /api/appointments returns authenticated user appointments', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');
    Appointment::factory()->count(2)->create(['user_id' => $user->id]);

    $other = User::factory()->create();
    Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson('/api/appointments');

    $response->assertOk()
        ->assertJsonCount(2, 'data');
});

it('GET /api/appointments requires auth', function () {
    $this->getJson('/api/appointments')->assertUnauthorized();
});

it('GET /api/appointments/{id} returns appointment detail', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->getJson("/api/appointments/{$appointment->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});

it('GET /api/appointments/{id} returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->getJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();
});

it('PUT /api/appointments/{id} updates notes on pending appointment', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $appointment = Appointment::factory()->create([
        'user_id'     => $user->id,
        'staff_id'    => $staff->id,
        'service_ids' => [$service->id],
        'status'      => 'pending',
        'notes'       => 'original note',
    ]);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'notes' => 'updated note',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.notes', 'updated note');
});

it('PUT /api/appointments/{id} returns 422 if not pending', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create([
        'user_id' => $user->id,
        'status'  => 'confirmed',
    ]);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'notes' => 'changed',
    ]);

    $response->assertUnprocessable();
});

it('PUT /api/appointments/{id} validates availability when changing date', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $appointment = Appointment::factory()->create([
        'user_id'     => $user->id,
        'staff_id'    => $staff->id,
        'service_ids' => [$service->id],
        'status'      => 'pending',
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('validateAvailability')
        ->andReturn(false);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'scheduled_date' => now()->addDays(5)->toDateTimeString(),
    ]);

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Staff non disponibile per questa data e ora.');
});

it('DELETE /api/appointments/{id} cancels appointment', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('cancelAppointment')
        ->with($appointment->id, null)
        ->once();

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertOk()
        ->assertJsonPath('message', 'Appuntamento cancellato con successo.');
});

it('DELETE /api/appointments/{id} returns 422 on BookingException', function () {
    $user        = User::factory()->create();
    $user->assignRole('customer');
    $appointment = Appointment::factory()->create(['user_id' => $user->id]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('cancelAppointment')
        ->andThrow(new BookingException('Impossibile cancellare meno di 24 ore prima.'));

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Impossibile cancellare meno di 24 ore prima.');
});

it('DELETE /api/appointments/{id} returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $other->id]);

    $response = $this->actingAs($user)->deleteJson("/api/appointments/{$appointment->id}");

    $response->assertForbidden();
});

it('PUT /api/appointments/{id} returns 403 for another user appointment', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $other = User::factory()->create();
    $appointment = Appointment::factory()->create([
        'user_id' => $other->id,
        'status'  => 'pending',
    ]);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'notes' => 'changed',
    ]);

    $response->assertForbidden();
});

it('PUT /api/appointments/{id} updates scheduled_date when availability allows', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $appointment = Appointment::factory()->create([
        'user_id'     => $user->id,
        'staff_id'    => $staff->id,
        'service_ids' => [$service->id],
        'status'      => 'pending',
    ]);
    $newDate = now()->addDays(10)->toDateTimeString();

    $this->mock(AppointmentService::class)
        ->shouldReceive('validateAvailability')
        ->andReturn(true);

    $response = $this->actingAs($user)->putJson("/api/appointments/{$appointment->id}", [
        'scheduled_date' => $newDate,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.id', $appointment->id);
});
