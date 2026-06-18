<?php

use App\Models\Appointment;
use App\Models\Business;
use App\Models\Payment;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $this->business = Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);
});

it('redirects guest to login', function () {
    $this->get('/admin/login')->assertOk();
});

it('returns 200 for admin', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)->get('/admin/' . $this->business->subdomain . '/report')->assertSuccessful();
});

it('returns 403 for staff', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->businesses()->attach($this->business->id);

    $this->actingAs($staff)->get('/admin/' . $this->business->subdomain . '/report')->assertForbidden();
});

it('returns 403 for customer', function () {
    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');
    $customer->businesses()->attach($this->business->id);

    $this->actingAs($customer)->get('/admin/' . $this->business->subdomain . '/report')->assertForbidden();
});

it('shows revenue stats labels', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Incasso')
        ->assertSee('Appuntamenti')
        ->assertSee('Cancellazioni')
        ->assertSee('Staff top');
});

it('shows correct total revenue in range', function () {
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

    Livewire::test(\App\Filament\Widgets\Reports\RevenueStatsWidget::class)
        ->assertSee('120,00');
});

it('shows insight stats labels', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Incasso medio')
        ->assertSee('Clienti unici')
        ->assertSee('Servizio top')
        ->assertSee('In attesa');
});

it('counts unique customers correctly', function () {
    $admin     = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);
    $customer1 = User::factory()->create(['business_id' => $this->business->id]);
    $customer2 = User::factory()->create(['business_id' => $this->business->id]);

    Appointment::factory()->count(2)->create([
        'user_id'        => $customer1->id,
        'scheduled_date' => now()->startOfMonth()->addDays(1),
    ]);
    Appointment::factory()->create([
        'user_id'        => $customer2->id,
        'scheduled_date' => now()->startOfMonth()->addDays(2),
    ]);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Clienti unici');
});

it('shows revenue chart heading', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Incassi nel tempo');
});

it('shows appointments by status chart heading', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Appuntamenti per stato');
});

it('shows service breakdown chart heading', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Appuntamenti per servizio');
});

it('shows staff performance heading', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get('/admin/' . $this->business->subdomain . '/report')
        ->assertSee('Performance Staff');
});

it('shows staff member with revenue in performance table', function () {
    $staffMember = User::factory()->create(['name' => 'Marco Rossi', 'business_id' => $this->business->id]);
    $staffMember->assignRole('staff');

    $appt = Appointment::factory()->create([
        'staff_id'       => $staffMember->id,
        'scheduled_date' => now()->startOfMonth()->addDays(3),
        'status'         => 'completed',
    ]);
    Payment::factory()->create([
        'appointment_id' => $appt->id,
        'user_id'        => $appt->user_id,
        'amount'         => 85.00,
        'status'         => 'completed',
    ]);

    Livewire::test(\App\Filament\Widgets\Reports\StaffPerformanceWidget::class)
        ->assertSee('Marco Rossi')
        ->assertSee('85,00');
});
