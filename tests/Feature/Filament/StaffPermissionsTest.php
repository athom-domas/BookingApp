<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
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
