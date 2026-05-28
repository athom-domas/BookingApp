<?php

use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('admin can grant permissions to staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.staff_permissions', ['appointments.view_all', 'appointments.create'])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.create'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.view'))->toBeFalse();
});

it('admin can revoke permissions from staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['appointments.view_all', 'reports.view']);

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.staff_permissions', [])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeFalse();
    expect($staff->hasPermissionTo('reports.view'))->toBeFalse();
});

it('staff without appointments.create cannot create appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeFalse();
});

it('staff with appointments.create can create appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.create');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeTrue();
});

it('staff without view_all sees only own appointments in list', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $otherStaff = User::factory()->create(['business_id' => $this->business->id]);
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt])
        ->assertCanNotSeeTableRecords([$otherAppt]);
});

it('staff with view_all sees all appointments in list', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.view_all');

    $otherStaff = User::factory()->create(['business_id' => $this->business->id]);
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt, $otherAppt]);
});

it('seeder creates the 5 staff permissions', function () {
    // Delete existing permissions before running seeder
    Permission::whereIn('name', ['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'])->delete();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    (new \Database\Seeders\DatabaseSeeder)->run();

    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())->toBeTrue("Permission {$perm} missing");
    }
});
