<?php

namespace App\Services;

use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\SalonProfile;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class BusinessProvisioningService
{
    public function provision(Business $business, string $adminEmail): User
    {
        return DB::transaction(function () use ($business, $adminEmail) {
            $tempPassword = Str::random(12);

            $admin = User::create([
                'name'                 => 'Admin',
                'email'                => $adminEmail,
                'password'             => Hash::make($tempPassword),
                'business_id'          => $business->id,
                'must_change_password' => true,
            ]);
            $admin->assignRole('admin');
            $admin->businesses()->attach($business->id);
            $admin->plainPassword = $tempPassword;

            $this->createInfrastructure($business);

            return $admin;
        });
    }

    public function provisionWithExistingAdmin(Business $business, User $existingAdmin): void
    {
        DB::transaction(function () use ($business, $existingAdmin) {
            $existingAdmin->businesses()->syncWithoutDetaching([$business->id]);
            $this->createInfrastructure($business);
        });
    }

    private function createInfrastructure(Business $business): void
    {
        SystemSetting::create([
            'business_id'                 => $business->id,
            'slot_generation_weeks'       => 4,
            'slot_granularity_minutes'    => 15,
            'timezone'                    => 'Europe/Rome',
            'booking_max_days_ahead'      => 30,
            'cancellation_deadline_hours' => 24,
            'reminder_count'              => 1,
            'reminder_1_hours'            => 24,
            'reminder_2_hours'            => 2,
            'payment_mode'                => 'both',
            'reviews_enabled'             => true,
        ]);

        SalonProfile::create([
            'business_id' => $business->id,
            'name'        => $business->name,
        ]);

        IntegrationSetting::create(['business_id' => $business->id]);

        foreach (['Taglio', 'Piega', 'Colore'] as $i => $name) {
            Service::create([
                'business_id'      => $business->id,
                'name'             => $name,
                'duration_minutes' => 30,
                'price'            => 20.00,
                'active'           => true,
                'featured'         => $i === 0,
            ]);
        }
    }
}
