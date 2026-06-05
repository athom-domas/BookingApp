<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyTransaction> */
class LoyaltyTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'        => 1,
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'appointment_id'     => null,
            'type'               => 'earn',
            'points'             => 10,
            'description'        => null,
        ];
    }
}
