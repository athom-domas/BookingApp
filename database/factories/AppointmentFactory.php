<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'    => 1,
            'user_id'        => User::factory(),
            'service_ids'    => fn () => [Service::factory()->create()->id],
            'staff_id'       => User::factory(),
            'scheduled_date' => now()->addDays(fake()->numberBetween(1, 30)),
            'status'         => 'pending',
            'final_price'    => null,
            'notes'          => null,
            'google_event_id' => null,
        ];
    }
}
