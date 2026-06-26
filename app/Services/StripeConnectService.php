<?php

namespace App\Services;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Models\SystemSetting;
use Stripe\StripeClient;

class StripeConnectService
{
    public function __construct(private readonly ?StripeClient $stripe) {}

    public function createAccount(Business $business): StripeConnectAccount
    {
        if (! $this->stripe) {
            throw new \App\Exceptions\BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
        }

        $existing = StripeConnectAccount::where('business_id', $business->id)->first();
        if ($existing) {
            return $existing;
        }

        $stripeAccount = $this->stripe->accounts->create([
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers'     => ['requested' => true],
            ],
            'metadata' => ['business_id' => $business->id],
        ]);

        try {
            return StripeConnectAccount::create([
                'business_id'       => $business->id,
                'stripe_account_id' => $stripeAccount->id,
                'mode'              => app()->environment('production') ? 'live' : 'test',
                'status'            => 'pending',
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return StripeConnectAccount::where('business_id', $business->id)->firstOrFail();
        }
    }

    public function createAccountLink(StripeConnectAccount $account, string $returnUrl, string $refreshUrl): string
    {
        if (! $this->stripe) {
            throw new \App\Exceptions\BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
        }

        $link = $this->stripe->accountLinks->create([
            'account'     => $account->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url'  => $returnUrl,
            'type'        => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function syncFromStripe(StripeConnectAccount $account): void
    {
        if (! $this->stripe) {
            throw new \App\Exceptions\BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
        }

        $stripeAccount = $this->stripe->accounts->retrieve($account->stripe_account_id);

        $requirements = $stripeAccount->requirements ?? null;
        $status = 'pending';

        if ($stripeAccount->charges_enabled) {
            $status = 'active';
        } elseif ($requirements?->disabled_reason) {
            $status = 'disabled';
        } elseif (! empty($requirements?->past_due)) {
            $status = 'restricted';
        } elseif ($stripeAccount->details_submitted) {
            $status = 'pending';
        }

        $account->update([
            'status'                      => $status,
            'charges_enabled'             => (bool) $stripeAccount->charges_enabled,
            'payouts_enabled'             => (bool) $stripeAccount->payouts_enabled,
            'details_submitted'           => (bool) $stripeAccount->details_submitted,
            'capabilities'                => $stripeAccount->capabilities ? $stripeAccount->capabilities->toArray() : null,
            'requirements_currently_due'  => $requirements?->currently_due ?? [],
            'requirements_past_due'       => $requirements?->past_due ?? [],
            'requirements_disabled_reason'=> $requirements?->disabled_reason,
            'default_currency'            => $stripeAccount->default_currency,
            'country'                     => $stripeAccount->country,
            'last_webhook_at'             => now(),
        ]);
    }

    public function createDashboardLink(StripeConnectAccount $account): string
    {
        if (! $this->stripe) {
            throw new \App\Exceptions\BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
        }

        $link = $this->stripe->accountLinks->create([
            'account'     => $account->stripe_account_id,
            'refresh_url' => route('stripe.connect.refresh'),
            'return_url'  => url('/admin/stripe-connect-page'),
            'type'        => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function calculatePlatformFee(Business $business, int $amountCents): array
    {
        $percent = $business->stripe_platform_fee_percent
            ?? SystemSetting::getStripePlatformFeePercent()
            ?? (float) config('services.stripe.platform_fee_percent', 0);

        $cents = (int) round($amountCents * $percent / 100);

        return ['cents' => $cents, 'percent' => (float) $percent];
    }
}
