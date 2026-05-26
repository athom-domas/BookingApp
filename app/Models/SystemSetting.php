<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slot_generation_weeks', 'slot_granularity_minutes', 'timezone', 'waitlist_offer_timeout_minutes'])]
class SystemSetting extends Model
{
    protected function casts(): array
    {
        return [
            'slot_generation_weeks'          => 'integer',
            'slot_granularity_minutes'       => 'integer',
            'waitlist_offer_timeout_minutes' => 'integer',
        ];
    }

    public static function current(): self
    {
        $existing = self::find(1);

        if ($existing) {
            return $existing;
        }

        $setting = new self([
            'slot_generation_weeks'          => 4,
            'slot_granularity_minutes'       => 10,
            'timezone'                       => 'Europe/Rome',
            'waitlist_offer_timeout_minutes' => 180,
        ]);
        $setting->id = 1;
        $setting->save();

        return $setting;
    }

    public static function getSlotGranularity(): int
    {
        return self::current()->slot_granularity_minutes;
    }

    public static function getTimezone(): string
    {
        return self::current()->timezone ?? 'Europe/Rome';
    }

    public static function getWaitlistOfferTimeout(): int
    {
        return self::current()->waitlist_offer_timeout_minutes ?? 180;
    }
}
