<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            ['name' => 'Admin', 'password' => Hash::make('password')]
        );
        $admin->syncRoles(['admin']);

        $staff = User::firstOrCreate(
            ['email' => 'staff@test.com'],
            ['name' => 'Staff', 'password' => Hash::make('password')]
        );
        $staff->syncRoles(['staff']);

        $customer = User::firstOrCreate(
            ['email' => 'customer@test.com'],
            ['name' => 'Customer', 'password' => Hash::make('password')]
        );
        $customer->syncRoles(['customer']);
    }
}
