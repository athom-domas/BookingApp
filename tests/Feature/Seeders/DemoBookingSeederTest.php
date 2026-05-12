<?php

use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;

it('seeds booking data needed by the customer portal', function () {
    $this->seed();

    expect(User::role('admin')->where('email', 'admin@test.com')->exists())->toBeTrue();
    expect(User::role('customer')->where('email', 'customer@test.com')->exists())->toBeTrue();
    expect(User::role('staff')->count())->toBeGreaterThanOrEqual(3);
    expect(Service::active()->whereHas('staff')->count())->toBeGreaterThanOrEqual(3);
    expect(AvailabilityRule::count())->toBeGreaterThanOrEqual(15);
    expect(TimeSlot::available()->whereDate('date', '>=', today())->count())->toBeGreaterThan(0);

    $slot = TimeSlot::available()
        ->whereDate('date', '>=', today())
        ->orderBy('date')
        ->orderBy('start_time')
        ->first();

    $service = $slot->user->services()
        ->active()
        ->where('duration_minutes', '<=', 60)
        ->first();

    $response = $this->getJson("/api/services/{$service->id}/slots?date={$slot->date->toDateString()}&staff_id={$slot->user_id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['start_time', 'end_time']]]);

    expect($response->json('data'))->not->toBeEmpty();
});
