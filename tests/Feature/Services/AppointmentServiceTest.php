<?php

use App\Exceptions\BookingException;
use App\Jobs\SendCancellationNotification;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Queue::fake();
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

function attachStaffToService(User $staff, Service $service): void
{
    $staff->assignRole('staff');
    $service->staff()->syncWithoutDetaching($staff->id);
}

function createBookableSlot(User $staff, Carbon $date, int $durationMinutes = 60): TimeSlot
{
    return TimeSlot::factory()->create([
        'user_id' => $staff->id,
        'date' => $date->format('Y-m-d'),
        'start_time' => $date->format('H:i:s'),
        'end_time' => $date->copy()->addMinutes($durationMinutes)->format('H:i:s'),
        'is_available' => true,
        'appointment_id' => null,
    ]);
}

// ── validateAvailability ──────────────────────────────────────────────────────

it('validateAvailability returns false when no rule exists for that day', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday')->setTime(10, 0);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday))->toBeFalse();
});

it('validateAvailability returns false when time is outside rule window', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday');
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(8, 0)))->toBeFalse();
    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(18, 0)))->toBeFalse();
});

it('validateAvailability returns true when slot is free', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday');
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(10, 0)))->toBeTrue();
});

it('validateAvailability returns false when appointment conflicts', function () {
    $staff = User::factory()->create();
    $existingService = Service::factory()->create(['duration_minutes' => 60]);
    $newService = Service::factory()->create(['duration_minutes' => 30]);
    attachStaffToService($staff, $existingService);
    attachStaffToService($staff, $newService);
    $monday = Carbon::parse('next monday');
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);
    // Existing appointment 10:00–11:00 + 15 buffer = blocks until 11:15
    Appointment::factory()->create([
        'staff_id' => $staff->id,
        'service_id' => $existingService->id,
        'scheduled_date' => $monday->copy()->setTime(10, 0),
        'status' => 'pending',
    ]);

    // New appointment at 10:30 (30 min) overlaps 10:00–11:15
    expect(app(AppointmentService::class)->validateAvailability($staff->id, $newService->id, $monday->copy()->setTime(10, 30)))->toBeFalse();
});

// ── bookAppointment ───────────────────────────────────────────────────────────

it('bookAppointment creates appointment with correct attributes', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 50.00]);
    $staff = User::factory()->create();
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday')->setTime(10, 0);
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);
    createBookableSlot($staff, $monday, 60);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    expect($appointment->status)->toBe('pending');
    expect((float) $appointment->final_price)->toBe(50.00);
    expect($appointment->user_id)->toBe($user->id);
    expect($appointment->staff_id)->toBe($staff->id);
});

it('bookAppointment creates a 24h reminder', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30, 'price' => 30.00]);
    $staff = User::factory()->create();
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday')->setTime(10, 0);
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);
    createBookableSlot($staff, $monday, 30);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    $reminder = AppointmentReminder::where('appointment_id', $appointment->id)->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->type)->toBe('email');
    expect($reminder->status)->toBe('pending');
    expect(Carbon::parse($reminder->scheduled_for)->format('Y-m-d H:i'))->toBe($monday->copy()->subDay()->format('Y-m-d H:i'));
});

it('bookAppointment marks existing time slot as unavailable', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 40.00]);
    $staff = User::factory()->create();
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday')->setTime(10, 0);
    AvailabilityRule::factory()->create([
        'user_id' => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time' => '17:00:00',
        'is_available' => true,
    ]);
    $slot = TimeSlot::factory()->create([
        'user_id' => $staff->id,
        'date' => Carbon::parse('next monday')->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time' => '11:00:00',
        'is_available' => true,
    ]);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    $slot->refresh();
    expect($slot->is_available)->toBeFalse();
    expect($slot->appointment_id)->toBe($appointment->id);
});

it('bookAppointment throws BookingException when staff is unavailable', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $staff = User::factory()->create();
    attachStaffToService($staff, $service);
    $monday = Carbon::parse('next monday')->setTime(10, 0);
    // No AvailabilityRule

    expect(fn () => app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday))
        ->toThrow(BookingException::class);
});

// ── cancelAppointment ─────────────────────────────────────────────────────────

it('cancelAppointment updates status to cancelled', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status' => 'pending',
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    expect($appointment->fresh()->status)->toBe('cancelled');
});

it('cancelAppointment frees the linked time slot', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status' => 'pending',
    ]);
    $slot = TimeSlot::factory()->create([
        'user_id' => $appointment->staff_id,
        'date' => now()->addDays(3)->format('Y-m-d'),
        'is_available' => false,
        'appointment_id' => $appointment->id,
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    $slot->refresh();
    expect($slot->is_available)->toBeTrue();
    expect($slot->appointment_id)->toBeNull();
});

it('cancelAppointment dispatches cancellation notification and calendar sync', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status' => 'pending',
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id, 'Cliente assente');

    Queue::assertPushed(SendCancellationNotification::class, fn ($job) => $job->appointment->id === $appointment->id);
    Queue::assertPushed(SyncGoogleCalendar::class, fn ($job) => $job->action === 'delete');
});

it('cancelAppointment throws BookingException within 24h', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addHours(12),
        'status' => 'pending',
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment->id))
        ->toThrow(BookingException::class);
});

it('cancelAppointment throws BookingException for completed appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status' => 'completed',
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment->id))
        ->toThrow(BookingException::class);
});

// ── getAvailableSlots ─────────────────────────────────────────────────────────

it('getAvailableSlots returns only slots that fit the service duration', function () {
    $staff = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    attachStaffToService($staff, $service);
    $date = now()->addDays(10)->format('Y-m-d');

    // 60-min slot: fits
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_available' => true]);
    // 30-min slot: too short
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'is_available' => true]);
    // 60-min slot but unavailable
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'is_available' => false]);

    $slots = app(AppointmentService::class)->getAvailableSlots($service->id, $staff->id, $date);

    expect($slots)->toHaveCount(1);
    expect($slots[0]['start_time'])->toBe('09:00:00');
});
