<?php

use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\AppointmentRescheduleService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'appointments.view_all', 'guard_name' => 'web']);

    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

function expectRescheduleReason(Appointment $appointment, Carbon $newTime, User $actor, string $expectedReason): void
{
    $caught = null;
    try {
        app(AppointmentRescheduleService::class)->reschedule($appointment, $newTime, $actor);
    } catch (RescheduleConflictException $e) {
        $caught = $e;
    }
    expect($caught)->not->toBeNull('Attesa RescheduleConflictException non lanciata');
    expect($caught->reason)->toBe($expectedReason);
}

function makeStaffWithService(): array
{
    $businessId = app('current_business_id');

    $staff = User::factory()->create(['business_id' => $businessId]);
    $staff->assignRole('staff');

    $service = Service::factory()->create([
        'business_id'      => $businessId,
        'duration_minutes' => 60,
        'active'           => true,
    ]);
    $staff->services()->attach($service->id);

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // martedì — 2026-06-23
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    return [$staff, $service];
}

function makeAppointment(User $staff, Service $service, string $status = 'pending', string $time = '10:00'): Appointment
{
    return Appointment::factory()->create([
        'business_id'    => app('current_business_id'),
        'staff_id'       => $staff->id,
        'user_id'        => User::factory()->create(['business_id' => app('current_business_id')])->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => Carbon::parse("2026-06-23 {$time}"),
        'status'         => $status,
    ]);
}

// ─── Test 1 ───────────────────────────────────────────────────────────────

it('reschedules a pending appointment to an available slot', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('14:00');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('14:00');
});

// ─── Test 2 ───────────────────────────────────────────────────────────────

it('reschedules a confirmed appointment to an available slot', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'confirmed', '10:00');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('14:00');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('14:00');
});

// ─── Test 3 ───────────────────────────────────────────────────────────────

it('allows admin to reschedule any appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);

    $admin = User::factory()->create(['business_id' => app('current_business_id')]);
    $admin->assignRole('admin');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 15:00'),
        $admin,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('15:00');
});

// ─── Test 4 ───────────────────────────────────────────────────────────────

it('allows staff with appointments.view_all to reschedule any appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);

    $otherStaff = User::factory()->create(['business_id' => app('current_business_id')]);
    $otherStaff->assignRole('staff');
    $otherStaff->givePermissionTo('appointments.view_all');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 15:00'),
        $otherStaff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('15:00');
});

// ─── Test 5 ───────────────────────────────────────────────────────────────

it('throws FORBIDDEN when staff tries to reschedule another staff appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    $otherStaff = User::factory()->create(['business_id' => app('current_business_id')]);
    $otherStaff->assignRole('staff');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $otherStaff,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 6 ───────────────────────────────────────────────────────────────

it('throws FORBIDDEN when actor belongs to a different business', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    $otherBusiness = Business::factory()->create();
    $actor = User::factory()->create(['business_id' => $otherBusiness->id]);
    $actor->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $actor,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 7 ───────────────────────────────────────────────────────────────

it('throws WRONG_STATUS when rescheduling a completed appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'completed');
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 8 ───────────────────────────────────────────────────────────────

it('throws WRONG_STATUS when rescheduling a cancelled appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'cancelled');
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 9 ───────────────────────────────────────────────────────────────

it('throws OUTSIDE_HOURS when slot is outside working hours', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 08:00'), // prima delle 09:00
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 10 ──────────────────────────────────────────────────────────────

it('throws OUTSIDE_HOURS when slot falls within a staff blockout', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    StaffBlockout::factory()->create([
        'user_id'     => $staff->id,
        'business_id' => app('current_business_id'),
        'start_date'  => '2026-06-23',
        'end_date'    => '2026-06-23',
        'start_time'  => '13:00',
        'end_time'    => '14:00',
    ]);

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 13:00'),
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});

// ─── Test 11 ──────────────────────────────────────────────────────────────

it('throws CONFLICT when slot overlaps another appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Secondo appuntamento: 11:00–12:00 (60 min)
    makeAppointment($staff, $service, 'confirmed', '11:00');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 11:30'), // overlap con 11:00-12:00
        $staff,
        RescheduleConflictException::CONFLICT,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});

// ─── Test 12 ──────────────────────────────────────────────────────────────

it('does not conflict with itself when moved to an overlapping position', function () {
    [$staff, $service] = makeStaffWithService();
    // Appuntamento alle 10:00, 60 min → occupa 10:00–11:00
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Spostiamo alle 10:30 → 10:30–11:30
    // Senza auto-esclusione dal conflict check fallirebbe (10:00–11:00 apparirebbe "occupato")
    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 10:30'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('10:30');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:30');
});

// ─── Test 13 ──────────────────────────────────────────────────────────────

it('throws FORBIDDEN when appointment business differs from actor business', function () {
    [$staff, $service] = makeStaffWithService();

    $otherBusiness = Business::factory()->create();
    $otherStaff    = User::factory()->create(['business_id' => $otherBusiness->id]);
    $otherStaff->assignRole('staff');
    AvailabilityRule::factory()->create([
        'user_id'      => $otherStaff->id,
        'day_of_week'  => 2,
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    $appointment = Appointment::factory()->create([
        'business_id'    => $otherBusiness->id,
        'staff_id'       => $otherStaff->id,
        'user_id'        => User::factory()->create(['business_id' => $otherBusiness->id])->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => Carbon::parse('2026-06-23 10:00'),
        'status'         => 'pending',
    ]);
    $originalTime = $appointment->scheduled_date->format('H:i');

    // Actor è admin del business principale, non di $otherBusiness
    $admin = User::factory()->create(['business_id' => app('current_business_id')]);
    $admin->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $admin,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});
