<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    SystemSetting::create([
        'id'                           => 1,
        'slot_generation_weeks'        => 4,
        'slot_granularity_minutes'     => 30,
        'hold_duration_minutes'        => 5,
        'hold_extension_minutes'       => 5,
        'min_service_duration_minutes' => 15,
        'timezone'                     => 'Europe/Rome',
    ]);
});

// ─── calculateTotalDuration ─────────────────────────────────────────────────

it('calculates total duration from active services', function () {
    $s1 = Service::factory()->create(['duration_minutes' => 30, 'active' => true]);
    $s2 = Service::factory()->create(['duration_minutes' => 45, 'active' => true]);

    $svc = new SlotCalculationService();
    expect($svc->calculateTotalDuration([$s1->id, $s2->id]))->toBe(75);
});

it('ignores inactive services in duration calculation', function () {
    $active   = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $inactive = Service::factory()->create(['duration_minutes' => 30, 'active' => false]);

    $svc = new SlotCalculationService();
    expect($svc->calculateTotalDuration([$active->id, $inactive->id]))->toBe(60);
});

it('returns zero duration for empty service list', function () {
    $svc = new SlotCalculationService();
    expect($svc->calculateTotalDuration([]))->toBe(0);
});

// ─── getEligibleOperators ────────────────────────────────────────────────────

it('returns staff who offer all requested services', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $s1 = Service::factory()->create(['active' => true]);
    $s2 = Service::factory()->create(['active' => true]);
    $staff->services()->attach([$s1->id, $s2->id]);

    $svc     = new SlotCalculationService();
    $result  = $svc->getEligibleOperators([$s1->id, $s2->id]);

    expect($result->pluck('id'))->toContain($staff->id);
});

it('excludes staff who only offer some of the requested services', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $s1 = Service::factory()->create(['active' => true]);
    $s2 = Service::factory()->create(['active' => true]);
    $staff->services()->attach($s1->id); // only one service

    $svc    = new SlotCalculationService();
    $result = $svc->getEligibleOperators([$s1->id, $s2->id]);

    expect($result->pluck('id'))->not->toContain($staff->id);
});

it('filters by specific staff id when preference is specific', function () {
    $staffA = User::factory()->create();
    $staffA->assignRole('staff');
    $staffB = User::factory()->create();
    $staffB->assignRole('staff');

    $service = Service::factory()->create(['active' => true]);
    $staffA->services()->attach($service->id);
    $staffB->services()->attach($service->id);

    $svc    = new SlotCalculationService();
    $result = $svc->getEligibleOperators([$service->id], $staffA->id, 'specific');

    expect($result->pluck('id'))->toContain($staffA->id)
        ->and($result->pluck('id'))->not->toContain($staffB->id);
});

// ─── getAvailableSlots ───────────────────────────────────────────────────────

it('returns empty when no service IDs given', function () {
    $svc = new SlotCalculationService();
    expect($svc->getAvailableSlots(['date' => now(), 'serviceIds' => []]))->toBe([]);
});

it('generates slots within staff availability window', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    // Monday 2026-05-18, day_of_week = 1
    $date = Carbon::parse('2026-05-18');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '12:00:00',
        'is_available' => true,
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    // First slot at window start, last slot such that it ends exactly at window end
    $last = end($slots);
    expect($slots)->not->toBeEmpty()
        ->and($slots[0]['start'])->toBe('09:00')
        ->and($slots[0]['end'])->toBe('10:00')
        ->and($last['start'])->toBe('11:00')
        ->and($last['end'])->toBe('12:00');
});

it('excludes slots blocked by confirmed appointments', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    $date = Carbon::parse('2026-05-18');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '12:00:00',
        'is_available' => true,
    ]);

    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => $date->copy()->setTime(9, 0),
        'status'         => 'confirmed',
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    $startTimes = array_column($slots, 'start');
    expect($startTimes)->not->toContain('09:00')
        ->and($startTimes)->not->toContain('09:30') // still blocked (appt ends at 10:00)
        ->and($startTimes)->toContain('10:00');
});

it('groups same time slots across multiple staff when preference is any', function () {
    $staffA = User::factory()->create();
    $staffA->assignRole('staff');
    $staffB = User::factory()->create();
    $staffB->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staffA->services()->attach($service->id);
    $staffB->services()->attach($service->id);

    $date = Carbon::parse('2026-05-18');
    foreach ([$staffA, $staffB] as $staff) {
        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => $date->dayOfWeek,
            'start_time'   => '09:00:00',
            'end_time'     => '10:00:00',
            'is_available' => true,
        ]);
    }

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffPreference' => 'any',
    ]);

    expect($slots)->toHaveCount(1)
        ->and($slots[0]['start'])->toBe('09:00')
        ->and($slots[0]['availableOperators'])->toHaveCount(2)
        ->and($slots[0]['availableOperators'])->toContain($staffA->id)
        ->and($slots[0]['availableOperators'])->toContain($staffB->id);
});

it('returns no slots when staff has no availability rule for that day', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    $date = Carbon::parse('2026-05-18'); // Monday

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    expect($slots)->toBe([]);
});

it('generates slots from both ranges when staff has a split schedule', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    // Morning 09:00–13:00, afternoon 14:00–18:00 on Monday
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 1,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '13:00:00',
        'start_time_2' => '14:00:00',
        'end_time_2'   => '18:00:00',
    ]);

    $date  = Carbon::parse('2026-05-18'); // Monday
    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    $times = array_column($slots, 'start');

    // Morning slots: 09:00, 09:30, 10:00, 10:30, 11:00, 11:30, 12:00
    expect($times)->toContain('09:00');
    expect($times)->toContain('12:00');

    // Afternoon slots: 14:00, 14:30, 15:00, 15:30, 16:00, 16:30, 17:00
    expect($times)->toContain('14:00');
    expect($times)->toContain('17:00');

    // Gap (13:00–14:00) should produce no slots (only 60 min, needs exactly 60 — edge case excluded by end boundary)
    expect($times)->not->toContain('13:00');
    expect($times)->not->toContain('13:30');
});

it('excludes past slots when date is today', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-20 10:15:00')); // Wednesday

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    $date = Carbon::today(); // 2026-05-20, day_of_week = 3
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '13:00:00',
        'is_available' => true,
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    Carbon::setTestNow(null);

    $startTimes = array_column($slots, 'start');

    expect($startTimes)->not->toContain('09:00')
        ->and($startTimes)->not->toContain('09:30')
        ->and($startTimes)->not->toContain('10:00')
        ->and($startTimes)->toContain('10:15') // first slot starts at now (clipped)
        ->and($startTimes)->toContain('11:45'); // last slot that fits (ends at 12:45 ≤ 13:00)
});

it('blocks time equal to combined duration of all service_ids on an appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service1 = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $service2 = Service::factory()->create(['duration_minutes' => 20, 'active' => true]);
    $staff->services()->attach([$service1->id, $service2->id]);

    $date = Carbon::parse('2026-05-19'); // Tuesday
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '08:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    // Appointment with two services stored in service_ids (total 80 min) starting at 08:00
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service1->id, $service2->id],
        'scheduled_date' => $date->copy()->setTime(8, 0),
        'status'         => 'confirmed',
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date->toDateString(),
        'serviceIds'      => [$service1->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    $startTimes = array_column($slots, 'start');

    // 80-min occupation ends at 09:20; slots before that must not exist
    expect($startTimes)->not->toContain('08:00')
        ->and($startTimes)->not->toContain('08:30')
        ->and($startTimes)->not->toContain('09:00')
        ->and($startTimes)->toContain('09:20');
});
