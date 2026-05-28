<?php

namespace App\Models;

use App\Models\Business;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'business_id',
    'stripe_public_key', 'stripe_secret_key', 'stripe_webhook_secret',
    'twilio_sid', 'twilio_token', 'twilio_from',
    'google_calendar_id', 'google_credentials_json',
])]
class IntegrationSetting extends Model
{
    use BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'stripe_secret_key'       => 'encrypted',
            'stripe_webhook_secret'   => 'encrypted',
            'twilio_sid'              => 'encrypted',
            'twilio_token'            => 'encrypted',
            'google_credentials_json' => 'encrypted',
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

    public static function getTwilioSid(): ?string
    {
        return self::current()->twilio_sid;
    }

    public static function getTwilioToken(): ?string
    {
        return self::current()->twilio_token;
    }

    public static function getTwilioFrom(): ?string
    {
        return self::current()->twilio_from;
    }

    public static function getGoogleCalendarId(): ?string
    {
        return self::current()->google_calendar_id;
    }

    public static function getGoogleCredentialsJson(): ?string
    {
        return self::current()->google_credentials_json;
    }
}
