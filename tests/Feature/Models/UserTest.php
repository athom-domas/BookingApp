<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('has many appointments as customer', function () {
    $user = User::factory()->create();
    Appointment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->appointmentsAsCustomer)->toHaveCount(2);
});

it('has many appointments as staff', function () {
    $staff = User::factory()->create();
    Appointment::factory()->count(3)->create(['staff_id' => $staff->id]);

    expect($staff->appointmentsAsStaff)->toHaveCount(3);
});

it('belongs to many services via service_staff', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();
    $service->staff()->attach($user->id);

    expect($user->services)->toHaveCount(1);
    expect($user->services->first()->id)->toBe($service->id);
});

it('has many availability rules', function () {
    $user = User::factory()->create();
    AvailabilityRule::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->availabilityRules)->toHaveCount(3);
});

it('has one preference', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create(['user_id' => $user->id]);

    expect($user->preferences)->toBeInstanceOf(UserPreference::class);
});

it('preference has a preferred staff user', function () {
    $staff = User::factory()->create();
    $preference = UserPreference::factory()->create(['preferred_staff' => $staff->id]);

    expect($preference->preferredStaff->id)->toBe($staff->id);
});

it('has many payments', function () {
    $user = User::factory()->create();
    Payment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->payments)->toHaveCount(2);
});

it('isAdmin returns true when user has admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->isAdmin())->toBeTrue();
    expect($user->isStaff())->toBeFalse();
    expect($user->isCustomer())->toBeFalse();
});

it('isStaff returns true when user has staff role', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect($user->isStaff())->toBeTrue();
});

it('isCustomer returns true when user has customer role', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    expect($user->isCustomer())->toBeTrue();
});
