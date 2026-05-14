<?php

use App\Models\AvailabilityRule;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('can store split time fields', function () {
    $staff = User::factory()->create()->assignRole('staff');

    $rule = AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '13:00:00',
        'start_time_2' => '14:00:00',
        'end_time_2'   => '18:00:00',
    ]);

    expect($rule->fresh())
        ->start_time_2->toBe('14:00:00')
        ->end_time_2->toBe('18:00:00');
});

it('allows null split times', function () {
    $staff = User::factory()->create()->assignRole('staff');

    $rule = AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2,
        'start_time_2' => null,
        'end_time_2'   => null,
    ]);

    expect($rule->fresh())
        ->start_time_2->toBeNull()
        ->end_time_2->toBeNull();
});
