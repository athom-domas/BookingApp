<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'appointment_id' => Appointment::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => 'pending',
            'payment_method' => 'stripe',
            'stripe_transaction_id' => null,
            'stripe_response' => null,
        ];
    }
}
