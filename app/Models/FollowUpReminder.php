<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<\Database\Factories\FollowUpReminderFactory> */
class FollowUpReminder extends Model
{
    use BelongsToBusiness, HasFactory;

    #[Fillable(['business_id', 'user_id', 'appointment_id', 'type', 'channel', 'delay_days',
                'scheduled_for', 'sent_at', 'status', 'processing_at', 'skipped_reason', 'error_message'])]

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at'       => 'datetime',
            'processing_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_for', '<=', now());
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->where('status', 'processing')
                     ->where('processing_at', '<=', now()->subMinutes(60));
    }
}
