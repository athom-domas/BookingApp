<?php

use App\Models\AvailabilityRule;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;

it('generates slots for a day with an availability rule', function () {
    $staff = User::factory()->create();
    $weekStart = Carbon::parse('2026-05-11');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '11:00:00',
        'is_available' => true,
    ]);

    $service = app(SlotGeneratorService::class);
    $count = $service->generateWeeklySlots($staff->id, $weekStart, 60);

    expect($count)->toBe(2);
    expect(TimeSlot::where('user_id', $staff->id)->count())->toBe(2);
});

it('skips days without availability rules', function () {
    $staff = User::factory()->create();
    $weekStart = Carbon::parse('2026-05-11');

    $count = app(SlotGeneratorService::class)->generateWeeklySlots($staff->id, $weekStart, 30);

    expect($count)->toBe(0);
});

it('skips slots conflicting with existing appointments', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $weekStart = Carbon::parse('2026-05-11');
    $monday  = $weekStart->copy();

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '11:00:00',
        'is_available' => true,
    ]);

    // Appointment at 09:00, duration 60 + 15 buffer = blocks until 10:15
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_id'     => $service->id,
        'scheduled_date' => $monday->copy()->setTime(9, 0),
        'status'         => 'pending',
    ]);

    // 30-min slots: 09:00, 09:30, 10:00 conflict; 10:30 free
    $count = app(SlotGeneratorService::class)->generateWeeklySlots($staff->id, $weekStart, 30);

    expect($count)->toBe(1);
    expect(TimeSlot::where('user_id', $staff->id)->orderBy('start_time')->first()->start_time)->toBe('10:30:00');
});

it('is idempotent when called twice', function () {
    $staff = User::factory()->create();
    $weekStart = Carbon::parse('2026-05-11');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'start_time'   => '09:00:00',
        'end_time'     => '10:00:00',
        'is_available' => true,
    ]);

    $svc = app(SlotGeneratorService::class);
    $svc->generateWeeklySlots($staff->id, $weekStart, 60);
    $second = $svc->generateWeeklySlots($staff->id, $weekStart, 60);

    expect($second)->toBe(0);
    expect(TimeSlot::where('user_id', $staff->id)->count())->toBe(1);
});
