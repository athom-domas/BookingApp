<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'slot_generation_weeks'    => 4,
            'slot_granularity_minutes' => 10,
            'timezone'                 => 'Europe/Rome',
        ];

        $setting = SystemSetting::first();

        $setting
            ? $setting->update($defaults)
            : SystemSetting::create($defaults);
    }
}
