<?php

use App\Models\{User, UserPreference, AvailabilityRule};
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->business = \App\Models\Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
    AvailabilityRule::factory()->create([
        'business_id'  => $this->business->id,
        'day_of_week'  => 1,
        'is_available' => true,
    ]);
});

it('saves booking preferences', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->patch(route('portal.settings.booking-preferences'), [
            'preferred_days'      => [1],
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '12:00',
        ])
        ->assertRedirect();

    $pref = UserPreference::where('user_id', $user->id)->first();
    expect($pref->preferred_days)->toBe([1])
        ->and($pref->preferred_time_from)->toStartWith('09:00')
        ->and($pref->preferred_time_to)->toStartWith('12:00');
});

it('rejects days not in open days', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->patch(route('portal.settings.booking-preferences'), [
            'preferred_days' => [0], // domenica, non aperta
        ])
        ->assertSessionHasErrors('preferred_days.0');
});

it('dismisses the preference prompt', function () {
    $user = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $this->actingAs($user)
        ->post(route('portal.settings.booking-preferences.dismiss'))
        ->assertRedirect();

    expect(UserPreference::where('user_id', $user->id)->first()->booking_preference_prompt_dismissed)->toBeTrue();
});
