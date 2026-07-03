<?php

use App\Models\Business;
use App\Models\UserPreference;

beforeEach(function () {
    Business::factory()->create(['id' => 1]);
});

it('casts preferred_days as array', function () {
    $pref = UserPreference::factory()->create(['preferred_days' => [1, 3, 5]]);
    expect($pref->fresh()->preferred_days)->toBe([1, 3, 5]);
});

it('preferred_days is nullable', function () {
    $pref = UserPreference::factory()->create(['preferred_days' => null]);
    expect($pref->fresh()->preferred_days)->toBeNull();
});

it('casts booking_preference_prompt_dismissed as bool', function () {
    $pref = UserPreference::factory()->create(['booking_preference_prompt_dismissed' => true]);
    expect($pref->fresh()->booking_preference_prompt_dismissed)->toBeTrue();
});
