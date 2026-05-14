<?php

use App\Models\User;
use App\Models\UserPreference;

it('slot_duration_minutes defaults to 60', function () {
    $user = User::factory()->create();
    $pref = UserPreference::factory()->create(['user_id' => $user->id]);

    expect($pref->slot_duration_minutes)->toBe(60);
});
