<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrderItem>
 */
class ProductOrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id'   => \App\Models\ProductOrder::factory(),
            'product_id' => \App\Models\Product::factory(),
            'quantity'   => fake()->numberBetween(1, 5),
            'unit_price' => fake()->randomFloat(2, 5, 100),
        ];
    }
}
