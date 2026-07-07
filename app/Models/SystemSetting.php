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
    'reviews_enabled',
    'loyalty_enabled', 'loyalty_points_per_euro', 'loyalty_reward_threshold', 'loyalty_reward_percentage',
    'low_stock_notify_user_ids',
    'order_notify_user_ids',
    'waitlist_offer_timeout_minutes',
    'review_request_enabled', 'review_request_delay_hours',
    'follow_up_reminders_enabled', 'follow_up_reminder_days',
    'stripe_platform_fee_percent',
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
            'reviews_enabled'             => 'boolean',
            'loyalty_enabled'           => 'boolean',
            'loyalty_points_per_euro'   => 'integer',
            'loyalty_reward_threshold'  => 'integer',
            'loyalty_reward_percentage' => 'integer',
            'low_stock_notify_user_ids'    => 'array',
            'order_notify_user_ids'        => 'array',
            'review_request_enabled'       => 'boolean',
            'review_request_delay_hours'   => 'integer',
            'follow_up_reminders_enabled'  => 'boolean',
            'follow_up_reminder_days'      => 'integer',
            'stripe_platform_fee_percent'  => 'float',
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
                'payment_mode'                => 'in_salon',
                'reviews_enabled'             => true,
                'review_request_enabled'      => false,
                'review_request_delay_hours'  => 2,
                'loyalty_enabled'           => false,
                'loyalty_points_per_euro'   => 1,
                'loyalty_reward_threshold'  => 100,
                'loyalty_reward_percentage' => 10,
                'follow_up_reminders_enabled' => false,
                'follow_up_reminder_days'     => 30,
                'stripe_platform_fee_percent' => null,
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
                'payment_mode'                => 'in_salon',
                'reviews_enabled'             => true,
                'review_request_enabled'      => false,
                'review_request_delay_hours'  => 2,
                'loyalty_enabled'           => false,
                'loyalty_points_per_euro'   => 1,
                'loyalty_reward_threshold'  => 100,
                'loyalty_reward_percentage' => 10,
                'follow_up_reminders_enabled' => false,
                'follow_up_reminder_days'     => 30,
                'stripe_platform_fee_percent' => null,
            ]
        );
    }

    public static function platform(): self
    {
        $setting = self::withoutGlobalScopes()
            ->whereNull('business_id')
            ->first();

        if ($setting) {
            return $setting;
        }

        return self::withoutEvents(
            fn () => self::withoutGlobalScopes()->create(['business_id' => null])
        );
    }

    public static function isReviewsEnabled(): bool
    {
        return self::current()->reviews_enabled ?? true;
    }

    public static function isReviewRequestEnabled(): bool
    {
        return self::current()->review_request_enabled ?? false;
    }

    public static function getReviewRequestDelayHours(): int
    {
        return self::current()->review_request_delay_hours ?? 2;
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

    public static function isLoyaltyEnabled(): bool
    {
        return self::current()->loyalty_enabled ?? false;
    }

    public static function getLoyaltyPointsPerEuro(): int
    {
        return self::current()->loyalty_points_per_euro ?? 1;
    }

    public static function getLoyaltyRewardThreshold(): int
    {
        return self::current()->loyalty_reward_threshold ?? 100;
    }

    public static function getLoyaltyRewardPercentage(): int
    {
        return self::current()->loyalty_reward_percentage ?? 10;
    }

    public static function getLowStockNotifyUserIds(): array
    {
        return self::current()->low_stock_notify_user_ids ?? [];
    }

    public static function getOrderNotifyUserIds(): array
    {
        return self::current()->order_notify_user_ids ?? [];
    }

    public static function getWaitlistOfferTimeout(): int
    {
        return self::current()->waitlist_offer_timeout_minutes ?? 180;
    }

    public static function isFollowUpRemindersEnabled(): bool
    {
        return self::current()->follow_up_reminders_enabled ?? false;
    }

    public static function getFollowUpReminderDays(): int
    {
        return self::current()->follow_up_reminder_days ?? 30;
    }

    public static function getStripePlatformFeePercent(): ?float
    {
        $v = self::platform()->stripe_platform_fee_percent;

        return $v !== null ? (float) $v : null;
    }
}
