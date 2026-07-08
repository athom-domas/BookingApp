<?php

namespace Database\Factories;

use App\Models\SalonProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalonProfile> */
class SalonProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'name' => fake()->company(),
            'meta_description' => fake()->paragraph(),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }
}
