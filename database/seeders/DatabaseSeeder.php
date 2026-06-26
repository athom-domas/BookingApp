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

        $business = Business::withoutGlobalScopes()->firstOrCreate(
            ['subdomain' => 'salone'],
            ['name' => 'Rossini Barbershop', 'status' => BusinessStatus::Active, 'trial_ends_at' => now()->addDays(14)],
        );
        app()->instance('current_business_id', $business->id);

        $this->call(RolesAndUsersSeeder::class, false, [
            'adminName'  => 'Luca Ferretti',
            'adminEmail' => 'admin@rossini.test',
        ]);
        $this->call(SystemSettingSeeder::class);
        $this->call(CurrentMonthSeeder::class);
        $this->call(SalonProfileSeeder::class, false, ['salonKey' => 'rossini']);
        $this->call(ProductSeeder::class,      false, ['salonKey' => 'rossini']);
        $this->call(PageBuilderSeeder::class);
    }
}
