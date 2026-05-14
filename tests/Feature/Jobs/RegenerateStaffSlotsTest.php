<?php

use App\Jobs\RegenerateStaffSlots;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\SystemSetting;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    SystemSetting::current()->update(['slot_generation_weeks' => 1]);
});

it('deletes future unbooked slots for the staff member', function () {
    $staff = User::factory()->create();

    $futureSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($futureSlot->id))->toBeNull();
});

it('preserves booked future slots (appointment_id not null)', function () {
    $staff = User::factory()->create();
    $appt = Appointment::factory()->create(['staff_id' => $staff->id]);

    $bookedSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => $appt->id,
        'is_available'   => false,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($bookedSlot->id))->not->toBeNull();
});

it('preserves past slots regardless of booking status', function () {
    $staff = User::factory()->create();

    $pastSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::yesterday(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($pastSlot->id))->not->toBeNull();
});

it('regenerates future slots using the new slot duration', function () {
    Carbon::setTestNow('2026-05-14 10:00:00'); // Thursday

    $staff = User::factory()->create();
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => Carbon::THURSDAY,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '11:00:00',
    ]);

    (new RegenerateStaffSlots($staff->id, 30))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    $slots = TimeSlot::where('user_id', $staff->id)
        ->whereDate('date', '2026-05-14')
        ->orderBy('start_time')
        ->get();

    expect($slots)->toHaveCount(4); // 09:00, 09:30, 10:00, 10:30 with 30-min slots

    Carbon::setTestNow();
});

it('generates slots for each week up to the configured horizon', function () {
    SystemSetting::current()->update(['slot_generation_weeks' => 2]);

    Carbon::setTestNow('2026-05-14 10:00:00'); // Thursday

    $staff = User::factory()->create();
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => Carbon::THURSDAY,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '10:00:00',
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    // This Thursday (2026-05-14) and next Thursday (2026-05-21) should both get slots
    $dates = TimeSlot::where('user_id', $staff->id)->pluck('date')->map->format('Y-m-d')->sort()->values();
    expect($dates->contains('2026-05-14'))->toBeTrue();
    expect($dates->contains('2026-05-21'))->toBeTrue();

    Carbon::setTestNow();
});

it('does not affect slots of other staff members', function () {
    $staff1 = User::factory()->create();
    $staff2 = User::factory()->create();

    $otherSlot = TimeSlot::factory()->create([
        'user_id'        => $staff2->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff1->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($otherSlot->id))->not->toBeNull();
});

it('failed hook logs the error', function () {
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('RegenerateStaffSlots failed', \Mockery::on(fn ($ctx) =>
            isset($ctx['staff_id']) && isset($ctx['error'])
        ));

    $job = new RegenerateStaffSlots(1, 60);
    $job->failed(new \Exception('DB error'));
});
