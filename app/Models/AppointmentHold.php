<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_id', 'session_id', 'customer_id', 'starts_at', 'ends_at', 'service_ids', 'status', 'expires_at'])]
class AppointmentHold extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentHoldFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'starts_at'   => 'datetime',
            'ends_at'     => 'datetime',
            'expires_at'  => 'datetime',
            'service_ids' => 'array',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('starts_at', $date);
    }

    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && ! $this->isExpired();
    }

    public function getDurationMinutes(): int
    {
        return $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function extend(int $minutes = 5): void
    {
        $this->expires_at = $this->expires_at->addMinutes($minutes);
        $this->save();
    }

    public function markAsConverted(): void
    {
        $this->update(['status' => 'converted']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    public function markAsAbandoned(): void
    {
        $this->update(['status' => 'abandoned']);
    }
}
