<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\UserPreference>
 */
class UserPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'notification_channel' => 'email',
            'phone_number'         => null,
        ];
    }
}
