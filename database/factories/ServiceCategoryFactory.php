<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceCategory>
 */
class ServiceCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'name'        => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'sort_order'  => 0,
            'is_active'   => true,
        ];
    }
}
