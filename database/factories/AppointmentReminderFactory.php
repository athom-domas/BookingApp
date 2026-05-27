<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AppointmentReminder>
 */
class AppointmentReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => 1,
            'appointment_id' => Appointment::factory(),
            'type' => fake()->randomElement(['email', 'sms']),
            'scheduled_for' => now()->addHours(fake()->numberBetween(1, 48)),
            'sent_at' => null,
            'status' => 'pending',
            'error_message' => null,
        ];
    }
}
