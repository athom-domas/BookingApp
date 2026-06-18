<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WaitlistEntry>
 */
class WaitlistEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'         => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'             => User::factory(),
            'service_ids'         => fn () => [Service::factory()->create()->id],
            'preferred_staff_id'  => null,
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '18:00',
            'preferred_days'      => [today()->addDay()->toDateString()],
            'status'              => 'waiting',
            'offered_slot'        => null,
        ];
    }
}
