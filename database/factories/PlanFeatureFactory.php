<?php

namespace Database\Factories;

use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanFeature> */
class PlanFeatureFactory extends Factory
{
    protected $model = PlanFeature::class;

    public function definition(): array
    {
        return [
            'key'         => $this->faker->unique()->slug(2),
            'label'       => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'min_plan'    => 'base',
        ];
    }
}
