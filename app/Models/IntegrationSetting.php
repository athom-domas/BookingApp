<?php

namespace App\Models;

use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'business_id',
    'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
'meta_whatsapp_token', 'meta_whatsapp_phone_id', 'meta_whatsapp_template',
    'google_calendar_id', 'google_credentials_json',
    'whatsapp_ai_enabled', 'whatsapp_ai_booking_enabled', 'whatsapp_ai_cancellation_enabled',
    'whatsapp_ai_custom_instructions', 'whatsapp_ai_handoff_email',
    'whatsapp_ai_timezone', 'whatsapp_ai_language', 'whatsapp_ai_max_turns',
    'whatsapp_notifications_enabled', 'whatsapp_monthly_limit', 'whatsapp_monthly_sent',
])]
class IntegrationSetting extends Model
{
    use BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'stripe_secret_key'       => 'encrypted',
            'stripe_webhook_secret'   => 'encrypted',
'meta_whatsapp_token'     => 'encrypted',
            'google_credentials_json' => 'encrypted',
            'whatsapp_notifications_enabled' => 'boolean',
        ];
    }

    public static function current(): self
    {
        if (! app()->bound('current_business_id')) {
            return new self();
        }

        return self::firstOrCreate(
            ['business_id' => Business::currentId()]
        );
    }

    public static function getStripePublicKey(): ?string
    {
        return self::current()->stripe_public_key;
    }

    public static function getStripeSecretKey(): ?string
    {
        return self::current()->stripe_secret_key;
    }

    public static function getStripeWebhookSecret(): ?string
    {
        return self::current()->stripe_webhook_secret;
    }

    public static function getMetaWhatsAppToken(): ?string
    {
        return self::current()->meta_whatsapp_token;
    }

    public static function getMetaWhatsAppPhoneId(): ?string
    {
        return self::current()->meta_whatsapp_phone_id;
    }

    public static function getMetaWhatsAppTemplate(): string
    {
        return self::current()->meta_whatsapp_template ?? 'appointment_reminder';
    }

    public static function hasMetaWhatsApp(): bool
    {
        $s = self::current();
        return ! empty($s->meta_whatsapp_token) && ! empty($s->meta_whatsapp_phone_id);
    }

    public static function getGoogleCalendarId(): ?string
    {
        return self::current()->google_calendar_id;
    }

    public static function getGoogleCredentialsJson(): ?string
    {
        return self::current()->google_credentials_json;
    }

    public static function findByPhoneNumberId(string $phoneNumberId): ?self
    {
        return self::withoutGlobalScope('business')->where('meta_whatsapp_phone_id', $phoneNumberId)->first();
    }

    public function hasWhatsAppAiEnabled(): bool
    {
        return (bool) $this->whatsapp_ai_enabled;
    }

    public function isWhatsAppBookingEnabled(): bool
    {
        return (bool) ($this->whatsapp_ai_booking_enabled ?? true);
    }

    public function isWhatsAppCancellationEnabled(): bool
    {
        return (bool) ($this->whatsapp_ai_cancellation_enabled ?? false);
    }

    public function getWhatsAppAiCustomInstructions(): ?string
    {
        return $this->whatsapp_ai_custom_instructions;
    }

    public function getWhatsAppAiHandoffEmail(): ?string
    {
        return $this->whatsapp_ai_handoff_email;
    }

    public function getWhatsAppAiTimezone(): string
    {
        return $this->whatsapp_ai_timezone ?? 'Europe/Rome';
    }

    public function getWhatsAppAiLanguage(): string
    {
        return $this->whatsapp_ai_language ?? 'it';
    }

    public function getWhatsAppAiMaxTurns(): int
    {
        return $this->whatsapp_ai_max_turns ?? 12;
    }

    public function hasWhatsAppNotificationsEnabled(): bool
    {
        return (bool) $this->whatsapp_notifications_enabled;
    }

    public function hasWhatsAppMonthlyCapacity(): bool
    {
        if ($this->whatsapp_monthly_limit === null) {
            return true;
        }

        return $this->whatsapp_monthly_sent < $this->whatsapp_monthly_limit;
    }
}
