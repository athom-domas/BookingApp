<?php

use App\Filament\Widgets\AppointmentCalendarWidget;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

$fetchRange = fn () => [
    'start' => now()->subDay()->toIso8601String(),
    'end'   => now()->addMonth()->toIso8601String(),
];

it('restituisce tutti gli appuntamenti per admin', function () use (&$fetchRange) {
    $admin  = User::factory()->create()->assignRole('admin');
    $staff1 = User::factory()->create()->assignRole('staff');
    $staff2 = User::factory()->create()->assignRole('staff');

    Appointment::factory()->create(['staff_id' => $staff1->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $staff2->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(2);
});

it('restituisce solo i propri appuntamenti per lo staff', function () use (&$fetchRange) {
    $staff = User::factory()->create()->assignRole('staff');
    $other = User::factory()->create()->assignRole('staff');

    $own = Appointment::factory()->create(['staff_id' => $staff->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $other->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($staff);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});
