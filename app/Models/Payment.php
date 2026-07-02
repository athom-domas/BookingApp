<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

#[Fillable(['business_id', 'appointment_id', 'user_id', 'amount', 'status', 'payment_method', 'stripe_transaction_id', 'stripe_response', 'loyalty_discount_percentage', 'loyalty_original_amount', 'stripe_account_id', 'platform_fee_amount', 'platform_fee_percent', 'stripe_charge_id', 'stripe_application_fee_id', 'stripe_transfer_id'])]
class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use BelongsToBusiness, HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'amount', 'payment_method'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->setDescriptionForEvent(fn(string $event) => "pagamento {$event}");
    }

    public function beforeActivityLogged(Activity $activity, string $eventName): void
    {
        $activity->business_id = $this->business_id;
        $activity->type        = 'activity';
        $activity->level       = $eventName === 'deleted' ? 'warning' : 'info';
        $activity->source      = 'model_event';
    }

    protected function casts(): array
    {
        return [
            'amount'                      => 'decimal:2',
            'platform_fee_amount'         => 'decimal:2',
            'platform_fee_percent'        => 'float',
            'loyalty_original_amount'     => 'decimal:2',
            'loyalty_discount_percentage' => 'integer',
            'stripe_response'             => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('payments.status', 'completed');
    }
}
