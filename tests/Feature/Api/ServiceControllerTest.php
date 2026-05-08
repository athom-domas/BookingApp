<?php

use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;

it('GET /api/services returns active services', function () {
    Service::factory()->count(3)->create(['active' => true]);
    Service::factory()->create(['active' => false]);

    $response = $this->getJson('/api/services');

    $response->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'duration_minutes', 'price']]]);
});

it('GET /api/services/{service}/slots returns available slots', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30, 'active' => true]);

    $this->mock(AppointmentService::class)
        ->shouldReceive('getAvailableSlots')
        ->with($service->id, $staff->id, '2026-06-01')
        ->andReturn([
            ['start_time' => '09:00:00', 'end_time' => '09:30:00'],
            ['start_time' => '09:30:00', 'end_time' => '10:00:00'],
        ]);

    $response = $this->getJson("/api/services/{$service->id}/slots?date=2026-06-01&staff_id={$staff->id}");

    $response->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['start_time', 'end_time']]]);
});

it('GET /api/services/{service}/slots validates required params', function () {
    $service = Service::factory()->create();

    $response = $this->getJson("/api/services/{$service->id}/slots");

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'staff_id']);
});
