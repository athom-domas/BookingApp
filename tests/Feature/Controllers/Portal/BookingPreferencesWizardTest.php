<?php

use App\Models\{User, UserPreference};
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->business = \App\Models\Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
});

it('passes bookingPreferences to view when customer has preferences', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    UserPreference::factory()->create([
        'user_id'             => $user->id,
        'business_id'         => $this->business->id,
        'preferred_days'      => [1, 3],
        'preferred_time_from' => '09:00:00',
        'preferred_time_to'   => '12:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', fn ($p) =>
            $p['days'] === [1, 3] && $p['timeFrom'] === '09:00'
        );
});

it('passes null bookingPreferences when customer has no preferences', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', null);
});

it('passes null bookingPreferences for unauthenticated visitors', function () {
    $this->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', null);
});

it('normalizes HH:MM:SS time values to HH:MM', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    UserPreference::factory()->create([
        'user_id'             => $user->id,
        'business_id'         => $this->business->id,
        'preferred_days'      => [2],
        'preferred_time_from' => '14:30:00',
        'preferred_time_to'   => '18:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('booking.create'))
        ->assertViewHas('bookingPreferences', fn ($p) =>
            $p['timeFrom'] === '14:30' && $p['timeTo'] === '18:00'
        );
});
