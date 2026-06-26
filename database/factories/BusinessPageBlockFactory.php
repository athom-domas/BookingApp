<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\BusinessPageBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BusinessPageBlock> */
class BusinessPageBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'  => Business::factory(),
            'block_type'   => 'hero',
            'variant'      => 'classic',
            'sort_order'   => $this->faker->numberBetween(0, 100),
            'is_enabled'   => true,
            'is_required'  => false,
            'is_locked'    => false,
            'content'      => [],
            'settings'     => [],
            'schema_version' => 1,
        ];
    }
}
