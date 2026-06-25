<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'user_id', 'notification_channel', 'phone_number',
    'follow_up_reminders_enabled', 'preferred_days',
    'preferred_time_from', 'preferred_time_to',
    'booking_preference_prompt_dismissed',
])]
class UserPreference extends Model
{
    /** @use HasFactory<\Database\Factories\UserPreferenceFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'follow_up_reminders_enabled'        => 'boolean',
            'preferred_days'                      => 'array',
            'booking_preference_prompt_dismissed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
