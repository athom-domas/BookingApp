<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FollowUpReminder>
 */
class FollowUpReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'    => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'        => User::factory(),
            'appointment_id' => Appointment::factory(),
            'type'           => 'rebooking',
            'channel'        => 'email',
            'delay_days'     => 30,
            'scheduled_for'  => now()->addDays(30),
            'sent_at'        => null,
            'status'         => 'pending',
            'processing_at'  => null,
            'skipped_reason' => null,
            'error_message'  => null,
        ];
    }
}
