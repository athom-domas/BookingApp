<?php

namespace App\Filament\Widgets\Reports;

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class LoyaltyStatsWidget extends Widget
{
    protected string $view = 'filament.widgets.reports.loyalty-stats';

    protected static ?int $sort = 11;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
    }

    public function getStats(): array
    {
        if (! SystemSetting::isLoyaltyEnabled()) {
            return ['enabled' => false];
        }

        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to = Carbon::parse($this->dateTo ?? now()->endOfMonth()->toDateString())->endOfDay();

        $activeMembers = LoyaltyAccount::where('points', '>', 0)->count();

        $earnTotal = LoyaltyTransaction::where('type', 'earn')
            ->whereBetween('created_at', [$from, $to])
            ->sum('points');

        $redeemTotal = LoyaltyTransaction::where('type', 'redeem')
            ->whereBetween('created_at', [$from, $to])
            ->sum('points');

        $reverseTotal = LoyaltyTransaction::where('type', 'reverse')
            ->whereBetween('created_at', [$from, $to])
            ->sum('points');

        $discountRow = Payment::completed()
            ->whereNotNull('loyalty_discount_percentage')
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$from, $to])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(payments.loyalty_original_amount - payments.amount), 0) as discount_total')
            ->first();

        $discountCount = (int) ($discountRow->cnt ?? 0);
        $discountTotal = (float) ($discountRow->discount_total ?? 0);

        $tiers = SystemSetting::getLoyaltyTiers();
        $tierBreakdown = $this->buildTierBreakdown($tiers);

        return [
            'enabled' => true,
            'activeMembers' => $activeMembers,
            'earnTotal' => (int) $earnTotal,
            'redeemTotal' => abs((int) $redeemTotal),
            'reverseTotal' => abs((int) $reverseTotal),
            'discountCount' => $discountCount,
            'discountTotal' => $discountTotal,
            'tierBreakdown' => $tierBreakdown,
            'hasTiers' => ! empty($tiers),
        ];
    }

    private function buildTierBreakdown(array $tiers): array
    {
        if (empty($tiers)) {
            return [];
        }

        usort($tiers, fn ($a, $b) => (int) ($a['threshold'] ?? 0) <=> (int) ($b['threshold'] ?? 0));

        $breakdown = [];
        foreach ($tiers as $i => $tier) {
            $threshold = (int) ($tier['threshold'] ?? 0);
            $minPoints = $threshold;
            $maxPoints = $i < count($tiers) - 1 ? (int) ($tiers[$i + 1]['threshold'] ?? PHP_INT_MAX) - 1 : PHP_INT_MAX;

            $count = LoyaltyAccount::where('points', '>=', $minPoints)
                ->where('points', '<=', $maxPoints)
                ->count();

            $label = $tier['name'] ?? ('Tier '.($i + 1));
            $pct = $tier['percentage'] ?? null;
            $amt = $tier['amount'] ?? null;

            $breakdown[] = [
                'label' => $label,
                'threshold' => $threshold,
                'reward' => $pct !== null ? "{$pct}%" : ($amt !== null ? "€{$amt}" : '—'),
                'count' => $count,
            ];
        }

        return $breakdown;
    }
}
