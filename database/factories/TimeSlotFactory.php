<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_available' => true,
            'appointment_id' => null,
        ];
    }
}
