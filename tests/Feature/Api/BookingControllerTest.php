<?php

use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use Carbon\Carbon;
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

// ─── GET /api/booking/available-dates ────────────────────────────────────────

it('returns available dates in a month for given services and staff', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 60]);
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    // Staff disponibile il lunedì
    $monday = Carbon::now()->dayOfWeek === Carbon::MONDAY
        ? Carbon::now()
        : Carbon::parse('next monday');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    $month = $monday->format('Y-m');

    $response = $this->getJson("/api/booking/available-dates?serviceIds[]={$service->id}&staffId={$staff->id}&month={$month}");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['success', 'data'])
        ->assertJsonPath('data.0', $monday->toDateString());
});

it('returns empty array when no staff is available in the month', function () {
    $service = Service::factory()->create(['active' => true]);

    $response = $this->getJson("/api/booking/available-dates?serviceIds[]={$service->id}&month=2026-01");

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', []);
});

it('validates required params for available-dates endpoint', function () {
    $this->getJson('/api/booking/available-dates')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serviceIds', 'month']);
});
