<?php

use App\Models\AppointmentHold;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;

it('seeds booking data needed by the customer portal', function () {
    $this->seed();

    expect(User::role('admin')->where('email', 'admin@test.com')->exists())->toBeTrue();
    expect(User::role('customer')->where('email', 'customer@test.com')->exists())->toBeTrue();
    expect(User::role('staff')->count())->toBeGreaterThanOrEqual(3);
    expect(Service::active()->whereHas('staff')->count())->toBeGreaterThanOrEqual(3);
    expect(AvailabilityRule::count())->toBeGreaterThanOrEqual(15);
    expect(AppointmentHold::count())->toBeGreaterThanOrEqual(3);

    // The API should return available slots for a staff member on a working day
    $staff = User::role('staff')->whereHas('availabilityRules', fn ($q) => $q->where('is_available', true))->first();
    $service = $staff->services()->active()->where('duration_minutes', '<=', 60)->first();

    // Find a future working day for this staff member
    $rule = $staff->availabilityRules()->where('is_available', true)->first();
    $date = Carbon::now()->next($rule->day_of_week)->toDateString();

    $response = $this->getJson("/api/services/{$service->id}/slots?date={$date}&staff_id={$staff->id}");

    $response->assertOk()
        ->assertJsonStructure(['data' => [['start_time', 'end_time']]]);

    expect($response->json('data'))->not->toBeEmpty();
});
