<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'receive_email_reminders', 'receive_sms_reminders', 'phone_number', 'timezone', 'preferred_staff', 'slot_duration_minutes'])]
class UserPreference extends Model
{
    /** @use HasFactory<\Database\Factories\UserPreferenceFactory> */
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_staff');
    }
}
