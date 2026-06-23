<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Booking\AppointmentService;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $business = \App\Models\Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    SystemSetting::create([
        'business_id'              => $business->id,
        'slot_generation_weeks'    => 4,
        'slot_granularity_minutes' => 30,
        'timezone'                 => 'Europe/Rome',
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

describe('bookDirect', function () {
    function makeBookDirectSetup(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $service = Service::factory()->create(['active' => true, 'duration_minutes' => 60, 'price' => 50.00]);
        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $service->staff()->attach($staff->id);

        $monday = Carbon::parse('next monday')->setTime(10, 0);

        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => 1,
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'is_available' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        return [$service, $staff, $customer, $monday];
    }

    it('creates a pending appointment when confirmImmediately is false', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'             => $customer->id,
            'serviceIds'         => [$service->id],
            'staffId'            => $staff->id,
            'scheduledDate'      => $monday,
            'confirmImmediately' => false,
        ]);

        expect($appointment->status)->toBe('pending');
        expect($appointment->staff_id)->toBe($staff->id);
        expect($appointment->service_ids)->toBe([$service->id]);
        expect((float) $appointment->final_price)->toBe(50.0);
    });

    it('creates a confirmed appointment when confirmImmediately is true', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'             => $customer->id,
            'serviceIds'         => [$service->id],
            'staffId'            => $staff->id,
            'scheduledDate'      => $monday,
            'confirmImmediately' => true,
        ]);

        expect($appointment->status)->toBe('confirmed');
    });

    it('sums prices for multiple services', function () {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $service1 = Service::factory()->create(['active' => true, 'duration_minutes' => 30, 'price' => 20.00]);
        $service2 = Service::factory()->create(['active' => true, 'duration_minutes' => 20, 'price' => 15.00]);

        $staff = User::factory()->create();
        $staff->assignRole('staff');
        $service1->staff()->attach($staff->id);
        $service2->staff()->attach($staff->id);

        $monday = Carbon::parse('next monday')->setTime(10, 0);

        AvailabilityRule::factory()->create([
            'user_id'      => $staff->id,
            'day_of_week'  => 1,
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'is_available' => true,
        ]);

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service1->id, $service2->id],
            'staffId'       => $staff->id,
            'scheduledDate' => $monday,
        ]);

        expect((float) $appointment->final_price)->toBe(35.0);
    });

    it('throws when the slot is not available', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        // Occupy the slot
        \App\Models\Appointment::factory()->create([
            'staff_id'       => $staff->id,
            'service_ids'    => [$service->id],
            'scheduled_date' => $monday,
            'status'         => 'confirmed',
        ]);

        expect(fn () => app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service->id],
            'staffId'       => $staff->id,
            'scheduledDate' => $monday,
        ]))->toThrow(\RuntimeException::class, 'Slot non disponibile');
    });

    it('assigns any available operator when staffId is null', function () {
        [$service, $staff, $customer, $monday] = makeBookDirectSetup();

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service->id],
            'staffId'       => null,
            'scheduledDate' => $monday,
        ]);

        expect($appointment->staff_id)->toBe($staff->id);
    });

    it('stores all service_ids on the appointment when booking multiple services', function () {
        [$service1, $staff, $customer, $monday] = makeBookDirectSetup();
        $service2 = Service::factory()->create(['active' => true, 'duration_minutes' => 20, 'price' => 10.00]);
        $service2->staff()->attach($staff->id);

        $appointment = app(\App\Services\Booking\AppointmentService::class)->bookDirect([
            'userId'        => $customer->id,
            'serviceIds'    => [$service1->id, $service2->id],
            'staffId'       => $staff->id,
            'scheduledDate' => $monday,
        ]);

        expect($appointment->service_ids)->toBe([$service1->id, $service2->id]);
    });
});
