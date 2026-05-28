<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff',       'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer',    'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $businessId = app('current_business_id');

        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Luca Ferretti', 'password' => Hash::make('password'), 'business_id' => $businessId]
        );
        $admin->syncRoles(['admin']);

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@test.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'business_id' => null]
        );
        $superAdmin->syncRoles(['super_admin']);
    }
}
