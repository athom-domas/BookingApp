<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'service_ids', 'preferred_staff_id',
    'preferred_date_from', 'preferred_date_to',
    'preferred_time_from', 'preferred_time_to',
    'preferred_days', 'status', 'offered_slot', 'offer_expires_at',
])]
class WaitlistEntry extends Model
{
    /** @use HasFactory<\Database\Factories\WaitlistEntryFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'service_ids'         => 'array',
            'preferred_days'      => 'array',
            'offered_slot'        => 'array',
            'preferred_date_from' => 'date',
            'preferred_date_to'   => 'date',
            'offer_expires_at'    => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_staff_id');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }
}
