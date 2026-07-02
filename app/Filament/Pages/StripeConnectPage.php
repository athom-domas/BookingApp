<?php

namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Filament\Pages\Page;

class StripeConnectPage extends Page
{
    protected string $view = 'filament.pages.stripe-connect';

    protected static ?string $navigationLabel = 'Pagamenti online';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Configurazioni';
    protected static ?int $navigationSort = 4;

    public function getConnectAccount(): ?StripeConnectAccount
    {
        return StripeConnectAccount::where('business_id', Business::currentId())->first();
    }

    public function getEffectiveFeePercent(): float
    {
        $business = \App\Models\Business::find(\App\Models\Business::currentId());
        if (! $business) {
            return (float) config('services.stripe.platform_fee_percent', 0);
        }
        return $business->stripe_platform_fee_percent
            ?? \App\Models\SystemSetting::getStripePlatformFeePercent()
            ?? (float) config('services.stripe.platform_fee_percent', 0);
    }

    public function startConnect(): void
    {
        $this->redirect(route('stripe.connect.start'), navigate: false);
    }

    public function refreshConnect(): void
    {
        $this->redirect(route('stripe.connect.refresh'), navigate: false);
    }

    public function openDashboard(): void
    {
        $account = $this->getConnectAccount();
        abort_unless($account, 403);

        $url = app(StripeConnectService::class)->createDashboardLink($account);

        $this->js("window.open(" . json_encode($url) . ", '_blank')");
    }

    public function getUiState(): string
    {
        $account = $this->getConnectAccount();

        if (! $account || ! $account->stripe_account_id) {
            return 'not_connected';
        }

        if (! $account->details_submitted) {
            return 'incomplete';
        }

        if ($account->status === 'disabled') {
            return 'disabled';
        }

        if ($account->status === 'restricted') {
            return 'restricted';
        }

        if ($account->charges_enabled) {
            return 'active';
        }

        return 'pending_review';
    }
}
