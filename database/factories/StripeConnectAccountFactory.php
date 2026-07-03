<?php

namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StripeConnectAccount> */
class StripeConnectAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'       => Business::factory(),
            'stripe_account_id' => 'acct_' . fake()->regexify('[A-Za-z0-9]{16}'),
            'mode'              => 'test',
            'status'            => 'active',
            'charges_enabled'   => true,
            'payouts_enabled'   => true,
            'details_submitted' => true,
            'country'           => 'IT',
            'default_currency'  => 'eur',
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status'            => 'pending',
            'charges_enabled'   => false,
            'details_submitted' => false,
            'stripe_account_id' => null,
        ]);
    }

    public function restricted(): static
    {
        return $this->state([
            'status'                => 'restricted',
            'charges_enabled'       => false,
            'requirements_past_due' => ['individual.dob.day'],
        ]);
    }
}
