<?php

use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;

it('GET /api/services returns active services', function () {
    $created = Service::factory()->count(3)->create(['active' => true]);
    Service::factory()->create(['active' => false]);

    $response = $this->getJson('/api/services');

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'description', 'duration_minutes', 'price']]]);

    $returnedIds = collect($response->json('data'))->pluck('id');
    foreach ($created->pluck('id') as $id) {
        expect($returnedIds)->toContain($id);
    }
});

it('GET /api/services/{service}/slots returns available slots', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30, 'active' => true]);
    $date = now()->addDays(5)->toDateString();

    $this->mock(AppointmentService::class)
        ->shouldReceive('getAvailableSlots')
        ->with($service->id, $staff->id, $date)
        ->andReturn([
            ['start_time' => '09:00:00', 'end_time' => '09:30:00'],
            ['start_time' => '09:30:00', 'end_time' => '10:00:00'],
        ]);

    $response = $this->getJson("/api/services/{$service->id}/slots?date={$date}&staff_id={$staff->id}");

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
