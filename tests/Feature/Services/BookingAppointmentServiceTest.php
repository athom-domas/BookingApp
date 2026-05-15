<?php

use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    SystemSetting::create([
        'slot_generation_weeks'        => 4,
        'slot_granularity_minutes'     => 30,
        'hold_duration_minutes'        => 10,
        'hold_extension_minutes'       => 5,
        'min_service_duration_minutes' => 15,
        'timezone'                     => 'Europe/Rome',
    ]);
});

function bookingMakeStaff(int $durationMinutes = 60): array
{
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $service = Service::factory()->create(['duration_minutes' => $durationMinutes, 'active' => true]);
    $staff->services()->attach($service->id);

    return [$staff, $service];
}

// ─── createHold ─────────────────────────────────────────────────────────────

it('creates an active hold for a valid available slot', function () {
    [$staff, $service] = bookingMakeStaff(60);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $date = Carbon::parse('2026-05-18');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    $this->actingAs($customer);

    $hold = app(AppointmentService::class)->createHold([
        'serviceIds'      => [$service->id],
        'date'            => $date->toDateString(),
        'slotStart'       => $date->copy()->setTime(10, 0)->toDateTimeString(),
        'slotEnd'         => $date->copy()->setTime(11, 0)->toDateTimeString(),
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
        'sessionId'       => 'test-session',
    ]);

    expect($hold)->toBeInstanceOf(AppointmentHold::class)
        ->and($hold->status)->toBe('active')
        ->and($hold->staff_id)->toBe($staff->id)
        ->and($hold->customer_id)->toBe($customer->id)
        ->and($hold->expires_at->gt(now()))->toBeTrue();
});

it('throws RuntimeException when slot is blocked by existing hold', function () {
    [$staff, $service] = bookingMakeStaff(60);

    $date = Carbon::parse('2026-05-18');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '11:00:00',
        'is_available' => true,
    ]);

    AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'other',
        'customer_id' => null,
        'starts_at'   => $date->copy()->setTime(9, 0),
        'ends_at'     => $date->copy()->setTime(10, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    expect(fn () => app(AppointmentService::class)->createHold([
        'serviceIds'      => [$service->id],
        'date'            => $date->toDateString(),
        'slotStart'       => $date->copy()->setTime(9, 0)->toDateTimeString(),
        'slotEnd'         => $date->copy()->setTime(10, 0)->toDateTimeString(),
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
        'sessionId'       => 'new-session',
    ]))->toThrow(RuntimeException::class, 'Slot no longer available');
});

it('throws RuntimeException when no service IDs given', function () {
    expect(fn () => app(AppointmentService::class)->createHold([
        'serviceIds'  => [],
        'date'        => now()->toDateString(),
        'slotStart'   => now()->setTime(10, 0)->toDateTimeString(),
        'slotEnd'     => now()->setTime(11, 0)->toDateTimeString(),
        'staffId'     => 1,
        'sessionId'   => 'x',
    ]))->toThrow(RuntimeException::class, 'No services selected');
});

// ─── extendHold ─────────────────────────────────────────────────────────────

it('extends hold TTL by specified minutes', function () {
    [$staff, $service] = bookingMakeStaff(60);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => null,
        'starts_at'   => now()->addHour(),
        'ends_at'     => now()->addHours(2),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(5),
    ]);

    $before = $hold->expires_at->copy();
    app(AppointmentService::class)->extendHold($hold, 5);

    expect($hold->fresh()->expires_at->gt($before))->toBeTrue();
});

it('throws RuntimeException when extending expired hold', function () {
    [$staff, $service] = bookingMakeStaff(60);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => null,
        'starts_at'   => now()->addHour(),
        'ends_at'     => now()->addHours(2),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->subMinute(),
    ]);

    expect(fn () => app(AppointmentService::class)->extendHold($hold))
        ->toThrow(RuntimeException::class, 'Hold is not active');
});

// ─── confirmFromHold ─────────────────────────────────────────────────────────

it('creates confirmed appointment from active hold and marks hold converted', function () {
    [$staff, $service] = bookingMakeStaff(60);
    $customer = User::factory()->create();

    $date = Carbon::parse('2026-05-18');
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => $customer->id,
        'starts_at'   => $date->copy()->setTime(10, 0),
        'ends_at'     => $date->copy()->setTime(11, 0),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    $appointment = app(AppointmentService::class)->confirmFromHold($hold);

    expect($appointment)->toBeInstanceOf(Appointment::class)
        ->and($appointment->status)->toBe('confirmed')
        ->and($appointment->staff_id)->toBe($staff->id)
        ->and($hold->fresh()->status)->toBe('converted');
});

it('throws RuntimeException when hold is already expired', function () {
    [$staff, $service] = bookingMakeStaff(60);

    $hold = AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'test',
        'customer_id' => null,
        'starts_at'   => now()->addHour(),
        'ends_at'     => now()->addHours(2),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->subMinute(),
    ]);

    expect(fn () => app(AppointmentService::class)->confirmFromHold($hold))
        ->toThrow(RuntimeException::class);
});

// ─── cancelAppointment ──────────────────────────────────────────────────────

it('cancels an upcoming appointment', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status'         => 'confirmed',
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment);

    expect($appointment->fresh()->status)->toBe('cancelled');
});

it('throws RuntimeException when appointment cannot be cancelled', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->subDay(),
        'status'         => 'pending',
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment))
        ->toThrow(RuntimeException::class);
});

// ─── cleanupExpiredHolds ────────────────────────────────────────────────────

it('marks only expired holds as expired', function () {
    [$staff, $service] = bookingMakeStaff(60);

    AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'a',
        'customer_id' => null,
        'starts_at'   => now()->addHour(),
        'ends_at'     => now()->addHours(2),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->subMinutes(5),
    ]);

    AppointmentHold::create([
        'staff_id'    => $staff->id,
        'session_id'  => 'b',
        'customer_id' => null,
        'starts_at'   => now()->addHour(),
        'ends_at'     => now()->addHours(2),
        'service_ids' => [$service->id],
        'status'      => 'active',
        'expires_at'  => now()->addMinutes(10),
    ]);

    $updated = AppointmentService::cleanupExpiredHolds();

    expect($updated)->toBe(1)
        ->and(AppointmentHold::where('status', 'active')->count())->toBe(1);
});
