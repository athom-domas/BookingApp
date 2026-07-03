<?php

namespace App\Filament\SuperAdmin\Pages;

use App\Models\Business;
use App\Models\Payment;
use App\Models\StripeConnectAccount;
use App\Models\SystemSetting;
use App\Services\StripeConnectService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class StripeConnectAdminPage extends Page
{
    protected string $view = 'filament.pages.stripe-connect-admin';

    protected static ?string $navigationLabel = 'Stripe Connect';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static ?int $navigationSort = 10;

    public string $period = '30d';
    public string $statusFilter = '';
    public bool $problemsOnly = false;
    public bool $withPaymentsOnly = false;
    public bool $feeOverrideOnly = false;
    public string $globalFeePercent = '0';

    public function mount(): void
    {
        $this->globalFeePercent = $this->formatFeePercent($this->getEffectiveGlobalFeePercent());
    }

    private function getPeriodStart(): ?Carbon
    {
        return match ($this->period) {
            'today' => now()->startOfDay(),
            '7d'    => now()->subDays(7)->startOfDay(),
            '30d'   => now()->subDays(30)->startOfDay(),
            'month' => now()->startOfMonth(),
            default => null,
        };
    }

    public function hasFees(): bool
    {
        if ($this->getEffectiveGlobalFeePercent() > 0) {
            return true;
        }
        if (Business::whereNotNull('stripe_platform_fee_percent')->where('stripe_platform_fee_percent', '>', 0)->exists()) {
            return true;
        }

        return Payment::withoutGlobalScopes()->where('platform_fee_amount', '>', 0)->exists();
    }

    public function getHeaderStats(): array
    {
        $periodStart = $this->getPeriodStart();

        $online = Payment::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereNotNull('stripe_account_id')
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->selectRaw('SUM(amount) as total_volume, SUM(platform_fee_amount) as total_fee, COUNT(*) as total_count')
            ->first();

        $offline = Payment::withoutGlobalScopes()
            ->where('status', 'completed')
            ->whereNull('stripe_account_id')
            ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
            ->selectRaw('SUM(amount) as total_volume, COUNT(*) as total_count')
            ->first();

        return [
            'volume_online'    => (float) ($online->total_volume ?? 0),
            'volume_offline'   => (float) ($offline->total_volume ?? 0),
            'fee'              => (float) ($online->total_fee ?? 0),
            'payments_online'  => (int) ($online->total_count ?? 0),
            'payments_offline' => (int) ($offline->total_count ?? 0),
            'active_salons'    => StripeConnectAccount::where('status', 'active')->where('charges_enabled', true)->count(),
            'problem_salons'   => StripeConnectAccount::whereIn('status', ['restricted', 'disabled'])->count(),
        ];
    }

    public function getAccounts()
    {
        $periodStart      = $this->getPeriodStart();
        $globalFeePercent = $this->getEffectiveGlobalFeePercent();

        $query = StripeConnectAccount::with('business')->latest();

        if ($this->problemsOnly) {
            $query->whereIn('status', ['restricted', 'disabled']);
        } elseif ($this->statusFilter) {
            $query->where('status', $this->statusFilter);
        }

        if ($this->feeOverrideOnly) {
            $query->whereHas('business', fn ($q) => $q->whereNotNull('stripe_platform_fee_percent'));
        }

        $accounts = $query->get()->map(function ($account) use ($periodStart, $globalFeePercent) {
            if ($account->stripe_account_id) {
                $row = Payment::withoutGlobalScopes()
                    ->where('stripe_account_id', $account->stripe_account_id)
                    ->where('status', 'completed')
                    ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
                    ->selectRaw('SUM(amount) as total_volume, SUM(platform_fee_amount) as total_fee, COUNT(*) as total_count')
                    ->first();

                $account->stats_volume = (float) ($row->total_volume ?? 0);
                $account->stats_fee    = (float) ($row->total_fee ?? 0);
                $account->stats_count  = (int) ($row->total_count ?? 0);
            } else {
                $account->stats_volume = 0.0;
                $account->stats_fee    = 0.0;
                $account->stats_count  = 0;
            }

            if ($account->business_id) {
                $offlineRow = Payment::withoutGlobalScopes()
                    ->where('business_id', $account->business_id)
                    ->where('status', 'completed')
                    ->whereNull('stripe_account_id')
                    ->when($periodStart, fn ($q) => $q->where('created_at', '>=', $periodStart))
                    ->selectRaw('SUM(amount) as total_volume, COUNT(*) as total_count')
                    ->first();

                $account->stats_volume_offline = (float) ($offlineRow->total_volume ?? 0);
                $account->stats_count_offline  = (int) ($offlineRow->total_count ?? 0);
            } else {
                $account->stats_volume_offline = 0.0;
                $account->stats_count_offline  = 0;
            }

            $account->effective_fee_percent = $account->business?->stripe_platform_fee_percent ?? $globalFeePercent;
            $account->has_fee_override      = $account->business?->stripe_platform_fee_percent !== null;

            return $account;
        });

        if ($this->withPaymentsOnly) {
            $accounts = $accounts->filter(
                fn ($a) => $a->stats_count > 0 || $a->stats_count_offline > 0
            );
        }

        return $accounts;
    }

    public function saveGlobalFee(): void
    {
        $data = $this->validate([
            'globalFeePercent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $percent = round((float) $data['globalFeePercent'], 2);

        SystemSetting::platform()->update([
            'stripe_platform_fee_percent' => $percent,
        ]);

        $this->globalFeePercent = $this->formatFeePercent($percent);

        Notification::make()
            ->title('Fee globale salvata')
            ->success()
            ->send();
    }

    public function resetGlobalFee(): void
    {
        SystemSetting::platform()->update([
            'stripe_platform_fee_percent' => null,
        ]);

        $this->globalFeePercent = $this->formatFeePercent($this->getEnvGlobalFeePercent());

        Notification::make()
            ->title('Fee globale ripristinata da env')
            ->success()
            ->send();
    }

    public function hasCustomGlobalFeePercent(): bool
    {
        return SystemSetting::platform()->stripe_platform_fee_percent !== null;
    }

    public function getEffectiveGlobalFeePercent(): float
    {
        return SystemSetting::getStripePlatformFeePercent()
            ?? $this->getEnvGlobalFeePercent();
    }

    public function getGlobalFeeSourceLabel(): string
    {
        return $this->hasCustomGlobalFeePercent()
            ? 'personalizzata'
            : 'env STRIPE_PLATFORM_FEE_PERCENT';
    }

    private function getEnvGlobalFeePercent(): float
    {
        return (float) config('services.stripe.platform_fee_percent', 0);
    }

    private function formatFeePercent(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.');
    }

    public function syncAccount(int $id): void
    {
        $account = StripeConnectAccount::findOrFail($id);
        app(StripeConnectService::class)->syncFromStripe($account);

        Notification::make()
            ->title('Account sincronizzato')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
