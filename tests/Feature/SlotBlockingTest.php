<?php

use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

it('excludes full day when blockout has no time range', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => null,
        'end_time'   => null,
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toBeEmpty();
});

it('subtracts time-range blockout from work ranges', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toHaveCount(2)
        ->and($ranges[0]['end']->format('H:i'))->toBe('13:00')
        ->and($ranges[1]['start']->format('H:i'))->toBe('14:00');
});

it('keeps work ranges untouched when time-range blockout is on a different day', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-24', // different day
        'end_date'   => '2026-06-24',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toHaveCount(1);
});
