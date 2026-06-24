<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['whatsapp_message_id', 'provider_message_id', 'status', 'payload', 'occurred_at'])]
class WhatsAppMessageStatus extends Model
{
    protected $table = 'whatsapp_message_statuses';
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'occurred_at' => 'datetime',
            'created_at'  => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(WhatsAppMessage::class, 'whatsapp_message_id');
    }
}
