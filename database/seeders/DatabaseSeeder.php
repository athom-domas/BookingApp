<?php

namespace Database\Seeders;

use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach ([
            'appointments.view_all',
            'appointments.create',
            'appointments.edit',
            'appointments.delete',
            'appointments.payments',
            'customers.view',
            'customers.create',
            'customers.edit',
            'customers.delete',
            'reports.view',
            'reports.view_revenue',
        ] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        Permission::where('name', 'payments.manage')->where('guard_name', 'web')->delete();

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@test.com'],
            ['name' => 'Super Admin', 'password' => Hash::make('password'), 'business_id' => null]
        );
        $superAdmin->syncRoles(['super_admin']);

        // Business 1 — created by migration 150000 as placeholder 'salone'
        $b1 = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
        $b1->update(['name' => 'Rossini Barbershop']);
        app()->instance('current_business_id', $b1->id);
        $this->seedBusiness('rossini', 'Luca Ferretti', 'admin@rossini.test');

        // Business 2
        $b2 = Business::withoutGlobalScopes()->updateOrCreate(
            ['subdomain' => 'chic'],
            ['name' => 'Chic Beauty Studio', 'status' => BusinessStatus::Active],
        );
        app()->instance('current_business_id', $b2->id);
        $this->seedBusiness('chic', 'Sara Colombo', 'admin@chic.test');
    }

    private function seedBusiness(string $salonKey, string $adminName, string $adminEmail): void
    {
        $this->call(RolesAndUsersSeeder::class, false, [
            'adminName'  => $adminName,
            'adminEmail' => $adminEmail,
        ]);
        $this->call(SystemSettingSeeder::class);
        $this->call(CurrentMonthSeeder::class, false, ['salonKey' => $salonKey]);
        $this->call(SalonProfileSeeder::class,  false, ['salonKey' => $salonKey]);
    }
}
