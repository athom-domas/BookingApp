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
            'user_id'             => User::factory(),
            'service_ids'         => fn () => [Service::factory()->create()->id],
            'preferred_staff_id'  => null,
            'preferred_date_from' => today()->addDay(),
            'preferred_date_to'   => today()->addDays(30),
            'preferred_time_from' => '09:00',
            'preferred_time_to'   => '18:00',
            'preferred_days'      => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            'status'              => 'waiting',
            'offered_slot'        => null,
            'offer_expires_at'    => null,
        ];
    }
}
