<?php

namespace Database\Factories;

use App\Models\PageTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageTemplate> */
class PageTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name'        => $this->faker->words(2, true),
            'slug'        => $this->faker->unique()->slug(),
            'description' => $this->faker->sentence(),
            'is_active'   => true,
            'is_default'  => false,
        ];
    }
}
