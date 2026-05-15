<?php

use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

// ─── GET /api/booking/slots ──────────────────────────────────────────────────

it('GET /api/booking/slots returns available slots', function () {
    $service = Service::factory()->create(['active' => true]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('getAvailableSlots')
        ->once()
        ->andReturn([
            ['start' => '09:00', 'end' => '10:00', 'availableOperators' => [1, 2]],
            ['start' => '10:00', 'end' => '11:00', 'availableOperators' => [1]],
        ]);

    $response = $this->getJson('/api/booking/slots?' . http_build_query([
        'date'       => now()->addDay()->format('Y-m-d'),
        'serviceIds' => [$service->id],
    ]));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.start', '09:00')
        ->assertJsonPath('data.0.end', '10:00')
        ->assertJsonPath('count', 2);
});

it('GET /api/booking/slots returns 422 when date is missing', function () {
    $service = Service::factory()->create(['active' => true]);

    $response = $this->getJson('/api/booking/slots?' . http_build_query([
        'serviceIds' => [$service->id],
    ]));

    $response->assertUnprocessable();
});

it('GET /api/booking/slots returns 422 when date is in the past', function () {
    $service = Service::factory()->create(['active' => true]);

    $response = $this->getJson('/api/booking/slots?' . http_build_query([
        'date'       => now()->subDay()->format('Y-m-d'),
        'serviceIds' => [$service->id],
    ]));

    $response->assertUnprocessable();
});

it('GET /api/booking/slots returns 422 when serviceIds is empty', function () {
    $response = $this->getJson('/api/booking/slots?' . http_build_query([
        'date'       => now()->addDay()->format('Y-m-d'),
        'serviceIds' => [],
    ]));

    $response->assertUnprocessable();
});

// ─── POST /api/booking/hold ──────────────────────────────────────────────────

it('POST /api/booking/hold creates a hold and returns 201', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['active' => true]);
    $date    = now()->addDay()->format('Y-m-d');

    $hold = AppointmentHold::make([
        'id'          => 1,
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => $user->id,
        'starts_at'   => now()->addDay()->setTime(10, 0),
        'ends_at'     => now()->addDay()->setTime(11, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('createHold')
        ->once()
        ->andReturn($hold);

    $response = $this->actingAs($user)->postJson('/api/booking/hold', [
        'serviceIds'      => [$service->id],
        'date'            => $date,
        'slotStart'       => '10:00',
        'slotEnd'         => '11:00',
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'active');
});

it('POST /api/booking/hold requires authentication', function () {
    $service = Service::factory()->create(['active' => true]);

    $this->postJson('/api/booking/hold', [
        'serviceIds' => [$service->id],
        'date'       => now()->addDay()->format('Y-m-d'),
        'slotStart'  => '10:00',
        'slotEnd'    => '11:00',
    ])->assertUnauthorized();
});

it('POST /api/booking/hold returns 400 when service throws', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $service = Service::factory()->create(['active' => true]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('createHold')
        ->once()
        ->andThrow(new \RuntimeException('Slot no longer available'));

    $response = $this->actingAs($user)->postJson('/api/booking/hold', [
        'serviceIds'  => [$service->id],
        'date'        => now()->addDay()->format('Y-m-d'),
        'slotStart'   => '10:00',
        'slotEnd'     => '11:00',
    ]);

    $response->assertBadRequest()
        ->assertJsonPath('success', false)
        ->assertJsonPath('error', 'Slot no longer available');
});

// ─── GET /api/booking/holds/{hold} ───────────────────────────────────────────

it('GET /api/booking/holds/{id} returns hold status', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $staff = User::factory()->create();
    $service = Service::factory()->create(['active' => true]);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => $user->id,
        'starts_at'   => now()->addDay()->setTime(10, 0),
        'ends_at'     => now()->addDay()->setTime(11, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    $response = $this->actingAs($user)->getJson("/api/booking/holds/{$hold->id}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $hold->id)
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('isExpired', false);
});

it('GET /api/booking/holds/{id} requires authentication', function () {
    $this->getJson('/api/booking/holds/1')->assertUnauthorized();
});

// ─── PUT /api/booking/holds/{hold}/extend ────────────────────────────────────

it('PUT /api/booking/holds/{id}/extend returns extended hold', function () {
    $user  = User::factory()->create();
    $user->assignRole('customer');
    $staff = User::factory()->create();
    $service = Service::factory()->create(['active' => true]);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => $user->id,
        'starts_at'   => now()->addDay()->setTime(10, 0),
        'ends_at'     => now()->addDay()->setTime(11, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(5),
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('extendHold')
        ->once()
        ->andReturn($hold->fresh());

    $response = $this->actingAs($user)->putJson("/api/booking/holds/{$hold->id}/extend");

    $response->assertOk()
        ->assertJsonPath('success', true);
});

// ─── POST /api/booking/confirm ───────────────────────────────────────────────

it('POST /api/booking/confirm creates appointment from hold', function () {
    $user    = User::factory()->create();
    $user->assignRole('customer');
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['active' => true]);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => $user->id,
        'starts_at'   => now()->addDay()->setTime(10, 0),
        'ends_at'     => now()->addDay()->setTime(11, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    $appointment = Appointment::factory()->create([
        'user_id'    => $user->id,
        'service_id' => $service->id,
        'staff_id'   => $staff->id,
        'status'     => 'confirmed',
    ]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('confirmFromHold')
        ->once()
        ->andReturn($appointment->load(['service', 'staff']));

    $response = $this->actingAs($user)->postJson('/api/booking/confirm', [
        'holdId' => $hold->id,
        'notes'  => 'Test note',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'confirmed');
});

it('POST /api/booking/confirm returns 422 when holdId is missing', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    $this->actingAs($user)->postJson('/api/booking/confirm', [])
        ->assertUnprocessable();
});
