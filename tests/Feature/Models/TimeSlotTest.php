<?php

use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Models\User;

it('belongs to a staff user', function () {
    $user = User::factory()->create();
    $slot = TimeSlot::factory()->create(['user_id' => $user->id]);

    expect($slot->user->id)->toBe($user->id);
});

it('belongs to an appointment when booked', function () {
    $appointment = Appointment::factory()->create();
    $slot = TimeSlot::factory()->create(['appointment_id' => $appointment->id, 'is_available' => false]);

    expect($slot->appointment->id)->toBe($appointment->id);
});

it('appointment is null when free', function () {
    $slot = TimeSlot::factory()->create(['appointment_id' => null]);

    expect($slot->appointment)->toBeNull();
});

it('scope available returns slots with no appointment and is_available true', function () {
    TimeSlot::factory()->create(['is_available' => true, 'appointment_id' => null]);
    TimeSlot::factory()->create(['is_available' => false, 'appointment_id' => null]);
    $booked = Appointment::factory()->create();
    TimeSlot::factory()->create(['is_available' => true, 'appointment_id' => $booked->id]);

    expect(TimeSlot::available()->count())->toBe(1);
});

it('scope forDate returns slots for the given date', function () {
    TimeSlot::factory()->create(['date' => '2026-06-01']);
    TimeSlot::factory()->create(['date' => '2026-06-02']);

    expect(TimeSlot::forDate('2026-06-01')->count())->toBe(1);
});
