<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyAccount> */
class LoyaltyAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'     => User::factory(),
            'points'      => 0,
        ];
    }
}
