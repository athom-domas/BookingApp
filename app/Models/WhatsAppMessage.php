<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'business_id', 'appointment_id', 'wamid', 'idempotency_key', 'phone', 'phone_normalized',
    'wa_id', 'profile_name', 'direction', 'type', 'template_name', 'status', 'payload',
    'conversation_id', 'processed_at', 'sent_at', 'failed_at', 'error_code', 'error_message',
])]
class WhatsAppMessage extends Model
{
    protected $table = 'whatsapp_messages';

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'processed_at' => 'datetime',
            'sent_at'      => 'datetime',
            'failed_at'    => 'datetime',
        ];
    }

    public function statuses(): HasMany
    {
        return $this->hasMany(WhatsAppMessageStatus::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeForAppointmentTemplate(Builder $query, int $appointmentId, string $templateName): Builder
    {
        return $query->where('appointment_id', $appointmentId)
                     ->where('template_name', $templateName);
    }

    public static function findByWamid(string $wamid): ?self
    {
        return self::where('wamid', $wamid)->first();
    }
}
