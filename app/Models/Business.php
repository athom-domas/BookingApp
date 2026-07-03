<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use App\Models\ActivityLog;
use App\Models\StripeConnectAccount;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'subdomain', 'status', 'trial_ends_at', 'stripe_platform_fee_percent'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, Billable;

    public function getForeignKey()
    {
        return 'business_id';
    }

    protected function casts(): array
    {
        return [
            'status'                    => BusinessStatus::class,
            'trial_ends_at'             => 'datetime',
            'stripe_platform_fee_percent' => 'float',
        ];
    }

    public static function currentId(): int
    {
        if (! app()->bound('current_business_id')) {
            throw new \RuntimeException('No current business context bound.');
        }

        return (int) app('current_business_id');
    }

    public function hasAccess(): bool
    {
        return $this->onGenericTrial() || $this->subscribed('default');
    }

    public function subscriptionStatus(): string
    {
        $sub = $this->subscription('default');
        if ($sub && ! $sub->onGracePeriod() && $this->subscribed('default')) {
            return 'active';
        }
        if ($sub?->onGracePeriod()) {
            return 'grace_period';
        }
        if ($this->onGenericTrial()) {
            return 'trial';
        }
        return 'expired';
    }

    public function users(): HasMany             { return $this->hasMany(User::class); }
    public function admins(): BelongsToMany      { return $this->belongsToMany(User::class); }
    public function services(): HasMany          { return $this->hasMany(Service::class); }
    public function appointments(): HasMany      { return $this->hasMany(Appointment::class); }
    public function systemSetting(): HasOne      { return $this->hasOne(SystemSetting::class); }
    public function salonProfile(): HasOne       { return $this->hasOne(SalonProfile::class); }
    public function integrationSetting(): HasOne { return $this->hasOne(IntegrationSetting::class); }

    public function stripeConnectAccount(): HasOne
    {
        return $this->hasOne(StripeConnectAccount::class);
    }

    public function canAcceptOnlinePayments(): bool
    {
        $account = $this->stripeConnectAccount;
        return $account !== null && $account->isActive();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
