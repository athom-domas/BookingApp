<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'service_id', 'staff_id', 'scheduled_date', 'status', 'final_price', 'notes', 'google_event_id'])]
class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'final_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_date', '>', now());
    }

    public function scopePastAppointments(Builder $query): Builder
    {
        return $query->where('scheduled_date', '<', now());
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', 'confirmed');
    }

    public function isPast(): bool
    {
        return $this->scheduled_date->isPast();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_date->isFuture();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) && $this->isUpcoming();
    }
}
