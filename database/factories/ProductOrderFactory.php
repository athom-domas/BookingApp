<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductOrder>
 */
class ProductOrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'    => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'        => \App\Models\User::factory(),
            'status'         => 'confirmed',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
        ];
    }
}
