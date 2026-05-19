<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('redirects guest to login', function () {
    $this->get('/admin/report')->assertRedirect('/admin/login');
});

it('returns 200 for admin', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/admin/report')->assertSuccessful();
});

it('returns 403 for staff', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)->get('/admin/report')->assertForbidden();
});

it('returns 403 for customer', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)->get('/admin/report')->assertForbidden();
});

it('shows revenue stats labels', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('Incasso totale')
        ->assertSee('Appuntamenti')
        ->assertSee('Tasso cancellazione')
        ->assertSee('Staff più produttivo');
});

it('shows correct total revenue in range', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $appt = Appointment::factory()->create([
        'scheduled_date' => now()->startOfMonth()->addDays(2),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $appt->id,
        'user_id'        => $appt->user_id,
        'amount'         => 120.00,
        'status'         => 'completed',
    ]);

    // Fuori range — non deve apparire nel totale
    $apptOld = Appointment::factory()->create([
        'scheduled_date' => now()->subMonths(2),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $apptOld->id,
        'user_id'        => $apptOld->user_id,
        'amount'         => 999.00,
        'status'         => 'completed',
    ]);

    $this->actingAs($admin)
        ->get('/admin/report')
        ->assertSee('120,00');
});
