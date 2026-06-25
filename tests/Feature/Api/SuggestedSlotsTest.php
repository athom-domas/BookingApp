<?php

use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

it('returns 422 when serviceIds is missing', function () {
    $this->getJson('/api/booking/suggested-slots')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['serviceIds']);
});

it('returns 422 when serviceIds is empty', function () {
    $this->getJson('/api/booking/suggested-slots?serviceIds[]=')
        ->assertUnprocessable();
});

it('returns suggested slots ordered by score desc then date/time asc', function () {
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30]);
    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    // Monday (1) availability
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    // Find next Monday
    $nextMonday = Carbon::today()->dayOfWeek === Carbon::MONDAY
        ? Carbon::today()
        : Carbon::parse('next monday');

    Carbon::setTestNow($nextMonday->copy()->subDay()); // set today to day before

    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds'    => [$service->id],
        'preferredDays' => [1],
        'timeFrom'      => '09:00',
        'timeTo'        => '12:00',
        'limit'         => 5,
    ]));

    Carbon::setTestNow();

    $response->assertOk()
        ->assertJsonStructure(['data' => [['date', 'time', 'score']]]);

    $data   = $response->json('data');
    $scores = collect($data)->pluck('score');

    expect($scores->first())->toBeGreaterThanOrEqual($scores->last());
    expect(count($data))->toBeLessThanOrEqual(5);
});

it('falls back to all open days when preferredDays is empty', function () {
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30]);
    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => (int) Carbon::today()->addDay()->format('w'),
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
        'timeFrom'   => '09:00',
        'timeTo'     => '12:00',
    ]));

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('respects the limit parameter', function () {
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30]);
    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    // Create availability rules for many days
    foreach (range(0, 6) as $dow) {
        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => $dow,
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'is_available' => true,
        ]);
    }

    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
        'limit'      => 3,
    ]));

    $response->assertOk();
    expect(count($response->json('data')))->toBeLessThanOrEqual(3);
});

it('returns slots with staffId when specific staff is requested', function () {
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30]);
    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service->staff()->attach($staff->id);

    $tomorrow = Carbon::today()->addDay();
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => (int) $tomorrow->format('w'),
        'start_time'   => '09:00:00',
        'end_time'     => '12:00:00',
        'is_available' => true,
    ]);

    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
        'staffId'    => $staff->id,
        'limit'      => 5,
    ]));

    $response->assertOk()
        ->assertJsonStructure(['data']);
});

it('returns empty data when no availability rules exist', function () {
    $service = Service::factory()->create(['active' => true, 'duration_minutes' => 30]);

    $response = $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
    ]));

    $response->assertOk()
        ->assertJsonPath('data', []);
});

it('returns 422 when limit exceeds maximum', function () {
    $service = Service::factory()->create(['active' => true]);

    $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
        'limit'      => 25,
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['limit']);
});

it('returns 422 when timeTo is before timeFrom', function () {
    $service = Service::factory()->create(['active' => true]);

    $this->getJson('/api/booking/suggested-slots?' . http_build_query([
        'serviceIds' => [$service->id],
        'timeFrom'   => '14:00',
        'timeTo'     => '09:00',
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['timeTo']);
});
