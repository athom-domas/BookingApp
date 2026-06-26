<?php

namespace Database\Factories;

use App\Models\PageTemplate;
use App\Models\PageTemplateBlock;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PageTemplateBlock> */
class PageTemplateBlockFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_template_id' => PageTemplate::factory(),
            'block_type'       => 'hero',
            'variant'          => 'classic',
            'sort_order'       => $this->faker->numberBetween(0, 100),
            'is_enabled'       => true,
            'is_required'      => false,
            'is_locked'        => false,
            'content'          => [],
            'settings'         => [],
            'schema_version'   => 1,
        ];
    }
}
