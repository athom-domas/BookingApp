<?php

use App\Filament\Resources\StaffResource\Pages\EditStaff;
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

it('seeder creates the 5 staff permissions', function () {
    // Delete existing permissions before running seeder
    Permission::whereIn('name', ['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'])->delete();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    (new \Database\Seeders\DatabaseSeeder)->run();

    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())->toBeTrue("Permission {$perm} missing");
    }
});
