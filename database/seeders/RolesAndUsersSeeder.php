<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    public function run(string $adminName, string $adminEmail): void
    {
        $admin = User::updateOrCreate(
            ['email' => $adminEmail],
            ['name' => $adminName, 'password' => Hash::make('password'), 'business_id' => app('current_business_id')]
        );
        $admin->syncRoles(['admin']);
        $admin->businesses()->syncWithoutDetaching([app('current_business_id')]);
    }
}
