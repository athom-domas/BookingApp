<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'stripe_refund_id', 'amount', 'status', 'reason',
    'refund_application_fee', 'reverse_transfer',
    'stripe_balance_transaction_id', 'payload',
])]
class StripeRefund extends Model
{
    /** @use HasFactory<\Database\Factories\StripeRefundFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount'                 => 'integer',
            'refund_application_fee' => 'boolean',
            'reverse_transfer'       => 'boolean',
            'payload'                => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
