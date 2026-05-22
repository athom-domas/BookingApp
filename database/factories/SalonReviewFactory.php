<?php

namespace Database\Factories;

use App\Models\SalonReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<SalonReview> */
class SalonReviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'author_name'  => fake()->name(),
            'body'         => fake()->paragraph(),
            'rating'       => fake()->numberBetween(3, 5),
            'is_published' => false,
            'sort_order'   => fake()->numberBetween(0, 100),
        ];
    }
}
