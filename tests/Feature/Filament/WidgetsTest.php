<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('dashboard shows stats widget labels', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Appuntamenti oggi')
        ->assertSee('Appuntamenti questo mese')
        ->assertSee('Ricavi del mese');
});

it('stats widget counts today appointments correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Appointment::factory()->count(3)->create(['scheduled_date' => today()]);
    Appointment::factory()->create(['scheduled_date' => today()->subMonth()]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSeeInOrder(['Appuntamenti oggi', '3']);
});

it('stats widget counts this month appointments correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Appointment::factory()->count(4)->create(['scheduled_date' => now()->startOfMonth()->addDays(2)]);
    Appointment::factory()->create(['scheduled_date' => now()->subMonths(2)]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSeeInOrder(['Appuntamenti questo mese', '4']);
});

it('stats widget sums completed payments for current month', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Payment::factory()->create(['status' => 'completed', 'amount' => 150.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 50.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'pending', 'amount' => 999.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 200.00, 'created_at' => now()->subMonth()]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('200,00'); // 150 + 50 = 200
});

it('dashboard shows latest appointments widget', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    Appointment::factory()->create([
        'user_id' => $customer->id,
        'scheduled_date' => today(),
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Mario Rossi')
        ->assertSee('Ultimi appuntamenti')
        ->assertSee('Cliente')
        ->assertSee('Staff')
        ->assertSee('Servizi');
});
