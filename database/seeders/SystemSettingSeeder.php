<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'slot_generation_weeks'       => 4,
            'slot_granularity_minutes'    => 10,
            'timezone'                    => 'Europe/Rome',
            'loyalty_enabled'             => true,
            'loyalty_points_per_euro'     => 1,
            'loyalty_reward_threshold'    => 100,
            'loyalty_reward_percentage'   => 10,
        ];

        $setting = SystemSetting::first();

        $setting
            ? $setting->update($defaults)
            : SystemSetting::create($defaults);
    }
}
