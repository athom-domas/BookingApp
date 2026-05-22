<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        $setting = SystemSetting::find(1);

        if ($setting) {
            $setting->update([
                'slot_generation_weeks'        => 4,
                'slot_granularity_minutes'     => 10,
                'min_service_duration_minutes' => 15,
                'timezone'                     => 'Europe/Rome',
            ]);
        } else {
            $setting = new SystemSetting([
                'slot_generation_weeks'        => 4,
                'slot_granularity_minutes'     => 10,
                'min_service_duration_minutes' => 15,
                'timezone'                     => 'Europe/Rome',
            ]);
            $setting->id = 1;
            $setting->save();
        }
    }
}
