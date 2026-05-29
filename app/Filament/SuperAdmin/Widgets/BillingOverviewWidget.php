<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Business;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $trialActive = Business::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))
            ->count();

        $activeSubscriptions = Business::whereHas(
            'subscriptions',
            fn ($q) => $q->where('stripe_status', 'active')
        )->count();

        $mrr = $activeSubscriptions * 29;

        $expired = Business::where(function ($q) {
            $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', now());
        })->whereDoesntHave('subscriptions', fn ($q) => $q->whereIn('stripe_status', ['active', 'trialing'])
            ->where(fn ($sq) => $sq->whereNull('ends_at')->orWhere('ends_at', '>', now()))
        )->count();

        return [
            Stat::make('Trial attivi', $trialActive)
                ->description('Saloni in periodo di prova')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Abbonamenti attivi', $activeSubscriptions)
                ->description('Saloni con piano attivo')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('MRR', '€' . number_format($mrr, 0, ',', '.'))
                ->description('Entrate mensili ricorrenti')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Scaduti', $expired)
                ->description('Trial terminato, nessun abbonamento')
                ->color('danger')
                ->icon('heroicon-o-exclamation-circle'),
        ];
    }
}
