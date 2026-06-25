<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'account_id', 'type', 'payload', 'processed_at', 'failed_at', 'error_message'])]
class StripeWebhookEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
            'failed_at'    => 'datetime',
            'created_at'   => 'datetime',
        ];
    }
}
