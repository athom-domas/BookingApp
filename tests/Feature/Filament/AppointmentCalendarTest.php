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

it('calcola end time dalla somma dei duration_minutes dei servizi', function () use (&$fetchRange) {
    $admin   = User::factory()->create()->assignRole('admin');
    $staff   = User::factory()->create()->assignRole('staff');
    $service1 = Service::factory()->create(['duration_minutes' => 30]);
    $service2 = Service::factory()->create(['duration_minutes' => 45]);

    $start = now()->addDays(1)->startOfHour();

    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'scheduled_date' => $start,
        'service_ids'    => [$service1->id, $service2->id],
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events[0]['end'])->toBe($start->copy()->addMinutes(75)->toIso8601String());
});

it('costruisce il titolo da nome cliente e nomi servizi', function () use (&$fetchRange) {
    $admin    = User::factory()->create()->assignRole('admin');
    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $staff    = User::factory()->create()->assignRole('staff');
    $service  = Service::factory()->create(['name' => 'Taglio']);

    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(1),
        'service_ids'    => [$service->id],
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events[0]['title'])->toBe('Mario Rossi – Taglio');
});

it('filtra gli eventi per staff quando admin imposta staffFilter', function () use (&$fetchRange) {
    $admin  = User::factory()->create()->assignRole('admin');
    $staff1 = User::factory()->create()->assignRole('staff');
    $staff2 = User::factory()->create()->assignRole('staff');

    $own = Appointment::factory()->create(['staff_id' => $staff1->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $staff2->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('staffFilter', $staff1->id)
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});

it('la pagina calendario è accessibile a admin', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/appointment-calendar')
        ->assertSuccessful();
});

it('la pagina calendario è accessibile a staff', function () {
    $staff = User::factory()->create()->assignRole('staff');

    $this->actingAs($staff)
        ->get('/admin/appointment-calendar')
        ->assertSuccessful();
});

it('il cambio stato aggiorna il record nel database', function () {
    $admin = User::factory()->create()->assignRole('admin');
    $staff = User::factory()->create()->assignRole('staff');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'status'   => 'pending',
        'scheduled_date' => now()->addDays(1),
    ]);

    $this->actingAs($admin);

    Livewire::test(AppointmentCalendarWidget::class)
        ->mountAction('changeStatus', ['appointmentId' => $appointment->id])
        ->set('mountedActions.0.data.status', 'confirmed')
        ->callMountedAction(['appointmentId' => $appointment->id])
        ->assertHasNoActionErrors();

    expect($appointment->fresh()->status)->toBe('confirmed');
});
