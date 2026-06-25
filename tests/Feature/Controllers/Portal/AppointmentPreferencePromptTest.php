<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use App\Models\UserPreference;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('shows preference prompt when customer has no preferences', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $user  = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $appt = Appointment::factory()->create([
        'business_id'    => $this->business->id,
        'user_id'        => $user->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(3),
        'status'         => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', true)
        ->assertSee('Vuoi salvare');
});

it('does not show prompt when preferences already exist', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $user  = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    UserPreference::factory()->create([
        'user_id'        => $user->id,
        'business_id'    => $this->business->id,
        'preferred_days' => [1, 2],
    ]);

    $appt = Appointment::factory()->create([
        'business_id'    => $this->business->id,
        'user_id'        => $user->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(3),
        'status'         => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', false);
});

it('does not show prompt when dismissed', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $user  = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    UserPreference::factory()->create([
        'user_id'                            => $user->id,
        'business_id'                        => $this->business->id,
        'preferred_days'                     => null,
        'booking_preference_prompt_dismissed' => true,
    ]);

    $appt = Appointment::factory()->create([
        'business_id'    => $this->business->id,
        'user_id'        => $user->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(3),
        'status'         => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('showPreferencePrompt', false);
});

it('calculates fascia label correctly for pomeriggio', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $user  = User::factory()->create(['business_id' => $this->business->id]);
    $user->assignRole('customer');

    $appt = Appointment::factory()->create([
        'business_id'    => $this->business->id,
        'user_id'        => $user->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->setTime(14, 30)->addDays(3),
        'status'         => 'confirmed',
    ]);

    $this->actingAs($user)
        ->get(route('portal.appointments.show', $appt))
        ->assertViewHas('prefillPreferences', fn ($p) => str_contains($p['label'], 'pomeriggio'));
});
