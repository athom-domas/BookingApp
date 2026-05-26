<?php

use App\Events\AppointmentCancelled;
use App\Jobs\NotifyWaitlistCandidateJob;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Models\WaitlistEntry;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('dispatches NotifyWaitlistCandidateJob when a matching entry exists', function () {
    Queue::fake();

    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when no entry matches the service', function () {
    Queue::fake();

    $service      = Service::factory()->create();
    $otherService = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$otherService->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when slot date is outside preferred range', function () {
    Queue::fake();

    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today()->addDays(10),
        'preferred_date_to'   => today()->addDays(20),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => today()->addDay()->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when slot time is outside preferred range', function () {
    Queue::fake();

    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '14:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when preferred day does not match', function () {
    Queue::fake();

    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['tuesday', 'thursday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when preferred_staff_id does not match', function () {
    Queue::fake();

    $staff1  = User::factory()->create();
    $staff2  = User::factory()->create();
    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_staff_id'  => $staff1->id,
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff2->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('dispatches when preferred_staff_id is null (any staff)', function () {
    Queue::fake();

    $staff   = User::factory()->create();
    $service = Service::factory()->create();

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_staff_id'  => null,
        'preferred_date_from' => today(),
        'preferred_date_to'   => today()->addDays(30),
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->next('Monday')->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertPushed(NotifyWaitlistCandidateJob::class);
});
