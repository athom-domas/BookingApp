<?php

namespace Database\Seeders;

use App\Models\Business;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
        app()->instance('current_business_id', $business->id);

        $this->call(RolesAndUsersSeeder::class);
        $this->call(SystemSettingSeeder::class);
        $this->call(CurrentMonthSeeder::class);
        $this->call(SalonProfileSeeder::class);
    }
}
