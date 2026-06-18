<?php

use App\Filament\Widgets\BookingStatsWidget;
use App\Filament\Widgets\LatestAppointmentsWidget;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $this->business = Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create(['business_id' => $this->business->id]);
    $this->admin->assignRole('admin');
    $this->admin->businesses()->attach($this->business->id);
});

it('dashboard shows stats widget labels', function () {
    $this->actingAs($this->admin)
        ->get('/admin/' . $this->business->subdomain)
        ->assertSuccessful()
        ->assertSee('Appuntamenti oggi')
        ->assertSee('Appuntamenti questo mese')
        ->assertSee('Ricavi del mese');
});

it('stats widget counts today appointments correctly', function () {
    Appointment::factory()->count(3)->create(['scheduled_date' => today()]);
    Appointment::factory()->create(['scheduled_date' => today()->subMonth()]);

    $this->actingAs($this->admin)
        ->get('/admin/' . $this->business->subdomain)
        ->assertSuccessful()
        ->assertSeeInOrder(['Appuntamenti oggi', '3']);
});

it('stats widget counts this month appointments correctly', function () {
    Appointment::factory()->count(4)->create(['scheduled_date' => now()->startOfMonth()->addDays(2)]);
    Appointment::factory()->create(['scheduled_date' => now()->subMonths(2)]);

    $this->actingAs($this->admin)
        ->get('/admin/' . $this->business->subdomain)
        ->assertSuccessful()
        ->assertSeeInOrder(['Appuntamenti questo mese', '4']);
});

it('stats widget sums completed payments for current month', function () {
    Payment::factory()->create(['status' => 'completed', 'amount' => 150.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 50.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'pending', 'amount' => 999.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 200.00, 'created_at' => now()->subMonth()]);

    $this->actingAs($this->admin);

    Livewire::test(BookingStatsWidget::class)
        ->assertSee('200,00'); // 150 + 50 = 200
});

it('dashboard shows latest appointments widget', function () {
    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'scheduled_date' => now()->addHour(),
    ]);

    $this->actingAs($this->admin);

    Livewire::test(LatestAppointmentsWidget::class)
        ->assertSee('Mario Rossi')
        ->assertSee('Ultimi appuntamenti')
        ->assertSee('Cliente')
        ->assertSee('Staff')
        ->assertSee('Servizi');
});
