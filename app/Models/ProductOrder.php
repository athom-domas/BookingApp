<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['business_id', 'user_id', 'status', 'payment_method', 'stripe_payment_intent_id', 'payment_status', 'notes'])]
class ProductOrder extends Model
{
    /** @use HasFactory<\Database\Factories\ProductOrderFactory> */
    use BelongsToBusiness, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'payment_status', 'payment_method'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $event) => "ordine prodotto {$event}");
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->business_id = $this->business_id;
        $activity->type        = 'activity';
        $activity->level       = 'info';
        $activity->source      = 'model_event';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductOrderItem::class, 'order_id');
    }

    public function getTotalAttribute(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) $item->unit_price * $item->quantity);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']);
    }
}
