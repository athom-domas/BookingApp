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

it('dispatches NotifyWaitlistCandidateJob for every matching entry', function () {
    Queue::fake();

    $staff   = User::factory()->create();
    $staff->assignRole('staff');
    $service = Service::factory()->create();

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertPushed(NotifyWaitlistCandidateJob::class, 2);
});

it('does not dispatch when no entry matches the service', function () {
    Queue::fake();

    $service      = Service::factory()->create();
    $otherService = Service::factory()->create();

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$otherService->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when appointment date not in preferred dates', function () {
    Queue::fake();

    $service = Service::factory()->create();

    $appointmentDate = today()->addDay();
    $futureDate1     = today()->addDays(10);
    $futureDate2     = today()->addDays(20);

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$futureDate1->toDateString(), $futureDate2->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when slot time is outside preferred range', function () {
    Queue::fake();

    $service = Service::factory()->create();

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '14:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch when appointment falls on a date not selected', function () {
    Queue::fake();

    $service = Service::factory()->create();

    $appointmentDate = now()->next('Monday');
    $otherDate       = now()->next('Wednesday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$otherDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
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

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_staff_id'  => $staff1->id,
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff2->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});

it('dispatches when preferred_staff_id is null (any staff)', function () {
    Queue::fake();

    $staff   = User::factory()->create();
    $service = Service::factory()->create();

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_staff_id'  => null,
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'waiting',
    ]);

    $appointment = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertPushed(NotifyWaitlistCandidateJob::class);
});

it('does not dispatch for a notified entry', function () {
    Queue::fake();

    $service = Service::factory()->create();

    $appointmentDate = now()->next('Monday');

    WaitlistEntry::factory()->create([
        'service_ids'         => [$service->id],
        'preferred_time_from' => '09:00',
        'preferred_time_to'   => '18:00',
        'preferred_days'      => [$appointmentDate->toDateString()],
        'status'              => 'notified',
    ]);

    $appointment = Appointment::factory()->create([
        'service_ids'    => [$service->id],
        'scheduled_date' => $appointmentDate->setTime(10, 0),
        'status'         => 'cancelled',
    ]);

    event(new AppointmentCancelled($appointment));

    Queue::assertNotPushed(NotifyWaitlistCandidateJob::class);
});
