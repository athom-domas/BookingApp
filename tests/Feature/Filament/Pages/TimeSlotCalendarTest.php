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

it('defaults to the current week start (Monday)', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSet('weekStart', now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'));
});

it('navigates to the previous week', function () {
    $expected = now()->startOfWeek(Carbon::MONDAY)->subWeek()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('previousWeek')
        ->assertSet('weekStart', $expected);
});

it('navigates to the next week', function () {
    $expected = now()->startOfWeek(Carbon::MONDAY)->addWeek()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('nextWeek')
        ->assertSet('weekStart', $expected);
});

it('shows prompt when no staff is selected', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSeeHtml('Seleziona uno staff');
});

it('loads slots for the selected staff and current week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => $monday->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('09:00')
        ->assertSee('09:30');
});

it('marks available slots with green class', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => $monday->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSeeHtml('bg-green-100');
});

it('does not load slots belonging to other staff', function () {
    $other = User::factory()->create();
    $other->assignRole('staff');

    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'    => $other->id,
        'date'       => $monday->format('Y-m-d'),
        'start_time' => '14:00:00',
        'end_time'   => '14:30:00',
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertDontSee('14:00');
});
