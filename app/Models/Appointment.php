<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Observers\AppointmentObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'service_ids', 'staff_id', 'scheduled_date', 'status', 'customer_confirmed_at', 'final_price', 'notes', 'google_event_id', 'business_id'])]
#[ObservedBy(AppointmentObserver::class)]
class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory, BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'final_price'    => 'decimal:2',
            'service_ids'    => 'array',
        ];
    }

    public function getServicesAttribute(): Collection
    {
        return Service::whereIn('id', $this->service_ids ?? [])->get();
    }

    public function getServicesLabelAttribute(): string
    {
        return $this->services->pluck('name')->implode(', ');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function loyaltyEarnTransaction(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class)->where('type', 'earn');
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
        if (! in_array($this->status, ['pending', 'confirmed'])) {
            return false;
        }

        // Appuntamenti in attesa di pagamento: nessun addebito, cancellabili sempre.
        if ($this->status === 'pending') {
            return true;
        }

        return now()->diffInHours($this->scheduled_date, false) >= SystemSetting::getCancellationDeadlineHours();
    }
}
