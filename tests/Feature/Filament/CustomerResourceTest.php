<?php

use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\RelationManagers\AppointmentsRelationManager;
use App\Filament\Resources\CustomerResource\RelationManagers\PaymentsRelationManager;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('shows only registered customers in the customer resource', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $customer = User::factory()->create(['email' => 'cliente@test.com']);
    $customer->assignRole('customer');
    $staff = User::factory()->create(['email' => 'staff@test.com']);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    $this->get(CustomerResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('cliente@test.com')
        ->assertDontSee('staff@test.com');
});

it('admin can write internal notes on a customer', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(EditCustomer::class, ['record' => $customer->id])
        ->set('data.name', $customer->name)
        ->set('data.email', $customer->email)
        ->set('data.internal_notes', 'Cliente preferisce appuntamenti al mattino.')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($customer->refresh()->internal_notes)->toBe('Cliente preferisce appuntamenti al mattino.');
});

it('customer detail page shows linked appointments and payments', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $customer = User::factory()->create();
    $customer->assignRole('customer');
    $staff = User::factory()->create(['name' => 'Operatore Test']);
    $staff->assignRole('staff');
    $service = Service::factory()->create(['name' => 'Consulenza Test']);
    $appointment = Appointment::factory()->create([
        'user_id'     => $customer->id,
        'staff_id'    => $staff->id,
        'service_ids' => [$service->id],
        'notes'       => 'Nota appuntamento visibile in scheda',
        'status'      => 'confirmed',
        'final_price' => 120,
    ]);
    Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'user_id' => $customer->id,
        'amount' => 120,
        'status' => 'completed',
        'stripe_transaction_id' => 'pi_customer_resource_test',
    ]);

    $this->actingAs($admin);

    $this->get(CustomerResource::getUrl('edit', ['record' => $customer]))
        ->assertSuccessful();

    Livewire::test(AppointmentsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->assertCanSeeTableRecords([$appointment])
        ->assertSee('Consulenza Test')
        ->assertSee('Operatore Test');

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $customer,
        'pageClass' => EditCustomer::class,
    ])
        ->assertCanSeeTableRecords([$appointment->payment])
        ->assertSee('pi_customer_resource_test');
});
