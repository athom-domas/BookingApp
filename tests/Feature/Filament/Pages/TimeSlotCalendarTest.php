<?php

use App\Filament\Pages\TimeSlotCalendar;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
});

it('renders the calendar page', function () {
    $this->get('/admin/time-slot-calendar')->assertOk();
});

it('denies access to non-admin users', function () {
    $this->actingAs($this->staff)
        ->get('/admin/time-slot-calendar')
        ->assertForbidden();
});

it('defaults to the first day of the current month', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSet('monthStart', now()->startOfMonth()->format('Y-m-d'));
});

it('navigates to the previous month', function () {
    $expected = now()->startOfMonth()->subMonth()->startOfMonth()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('previousMonth')
        ->assertSet('monthStart', $expected);
});

it('navigates to the next month', function () {
    $expected = now()->startOfMonth()->addMonth()->startOfMonth()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('nextMonth')
        ->assertSet('monthStart', $expected);
});

it('shows prompt when no staff is selected', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSeeHtml('Seleziona uno staff');
});

it('loads slots for the selected staff in the current month', function () {
    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => now()->startOfMonth()->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('disp.');
});

it('marks available slots with green badge', function () {
    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => now()->startOfMonth()->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('disp.')
        ->assertDontSee('occ.');
});

it('marks occupied slots with red badge', function () {
    TimeSlot::factory()->create([
        'user_id'      => $this->staff->id,
        'date'         => now()->startOfMonth()->format('Y-m-d'),
        'start_time'   => '10:00:00',
        'end_time'     => '10:30:00',
        'is_available' => false,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('occ.');
});

it('marks booked slots (appointment_id set) with red badge', function () {
    $appointment = \App\Models\Appointment::factory()->create([
        'staff_id' => $this->staff->id,
    ]);

    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => now()->startOfMonth()->format('Y-m-d'),
        'start_time'     => '11:00:00',
        'end_time'       => '11:30:00',
        'is_available'   => true,
        'appointment_id' => $appointment->id,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('occ.');
});

it('does not load slots belonging to other staff', function () {
    $other = User::factory()->create();
    $other->assignRole('staff');

    TimeSlot::factory()->create([
        'user_id'    => $other->id,
        'date'       => now()->startOfMonth()->format('Y-m-d'),
        'start_time' => '14:00:00',
        'end_time'   => '14:30:00',
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertDontSee('disp.')
        ->assertDontSee('occ.');
});
