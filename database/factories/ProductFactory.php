<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'         => 1,
            'name'                => fake()->words(3, true),
            'description'         => fake()->sentence(),
            'price'               => fake()->randomFloat(2, 5, 100),
            'stock'               => fake()->numberBetween(0, 50),
            'low_stock_threshold' => null,
            'in_sale'             => true,
            'active'              => true,
        ];
    }
}
