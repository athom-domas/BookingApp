<?php

use App\Models\Business;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

test('allows admin access when business is on trial', function () {
    $business = Business::factory()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertDontRedirect(route('filament.admin.pages.abbonamento'));
});

test('redirects admin to billing when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('filament.admin.pages.abbonamento'));
});

test('returns 403 for staff when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('staff');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('billing page itself is accessible even when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get(route('filament.admin.pages.abbonamento'))
        ->assertDontRedirect(route('filament.admin.pages.abbonamento'));
});
