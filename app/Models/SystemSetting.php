<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Business;

#[Fillable([
    'business_id',
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
])]
class SystemSetting extends Model
{
    use BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'slot_generation_weeks'       => 'integer',
            'slot_granularity_minutes'    => 'integer',
            'booking_max_days_ahead'      => 'integer',
            'cancellation_deadline_hours' => 'integer',
            'reminder_count'              => 'integer',
            'reminder_1_hours'            => 'integer',
            'reminder_2_hours'            => 'integer',
        ];
    }

    public static function current(): self
    {
        if (! app()->bound('current_business_id')) {
            return new self([
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 30,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'reminder_2_hours'            => 2,
                'payment_mode'                => 'both',
            ]);
        }

        return self::firstOrCreate(
            ['business_id' => Business::currentId()],
            [
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 30,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'reminder_2_hours'            => 2,
                'payment_mode'                => 'both',
            ]
        );
    }

    public static function getSlotGranularity(): int
    {
        return self::current()->slot_granularity_minutes;
    }

    public static function getTimezone(): string
    {
        return self::current()->timezone ?? 'Europe/Rome';
    }

    public static function getBookingMaxDaysAhead(): int
    {
        return self::current()->booking_max_days_ahead ?? 90;
    }

    public static function getCancellationDeadlineHours(): int
    {
        return self::current()->cancellation_deadline_hours ?? 24;
    }

    public static function getReminderCount(): int
    {
        return self::current()->reminder_count ?? 1;
    }

    public static function getReminder1Hours(): int
    {
        return self::current()->reminder_1_hours ?? 24;
    }

    public static function getReminder2Hours(): int
    {
        return self::current()->reminder_2_hours ?? 2;
    }

    public static function getPaymentMode(): string
    {
        return self::current()->payment_mode ?? 'both';
    }
}
