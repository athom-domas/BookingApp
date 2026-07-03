<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'stripe_account_id', 'mode', 'status',
    'charges_enabled', 'payouts_enabled', 'details_submitted',
    'capabilities', 'requirements_currently_due', 'requirements_past_due',
    'requirements_disabled_reason', 'default_currency', 'country',
    'onboarding_completed_at', 'last_webhook_at',
])]
class StripeConnectAccount extends Model
{
    /** @use HasFactory<\Database\Factories\StripeConnectAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'charges_enabled'            => 'boolean',
            'payouts_enabled'            => 'boolean',
            'details_submitted'          => 'boolean',
            'capabilities'               => 'array',
            'requirements_currently_due' => 'array',
            'requirements_past_due'      => 'array',
            'onboarding_completed_at'    => 'datetime',
            'last_webhook_at'            => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->charges_enabled === true
            && $this->stripe_account_id !== null;
    }
}
