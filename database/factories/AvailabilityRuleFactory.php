<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'  => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'      => User::factory(),
            'day_of_week'  => fake()->numberBetween(0, 6),
            'start_time'   => '09:00:00',
            'end_time'     => '17:00:00',
            'start_time_2' => null,
            'end_time_2'   => null,
            'is_available' => true,
        ];
    }
}
