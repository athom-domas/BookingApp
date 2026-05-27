<?php

namespace Database\Factories;

use App\Enums\BusinessStatus;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Business> */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'      => fake()->company(),
            'subdomain' => fake()->unique()->lexify('salon-????'),
            'status'    => BusinessStatus::Active,
        ];
    }

    public function suspended(): static
    {
        return $this->state(['status' => BusinessStatus::Suspended]);
    }
}
