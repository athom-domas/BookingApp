<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\On;

class InsightStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort      = 2;
    protected static bool $isLazy    = false;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getStats(): array
    {
        $from   = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to     = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $fromDt = $from->copy()->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        $paidRow = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(payments.amount), 0) as total')
            ->first();

        $paidCount    = (int) ($paidRow->cnt ?? 0);
        $totalRevenue = (float) ($paidRow->total ?? 0);

        $avgRevenue = $paidCount > 0 ? round($totalRevenue / $paidCount, 2) : 0;

        $uniqueCustomers = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->distinct('user_id')
            ->count('user_id');

        $pendingCount = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'pending')
            ->count();

        [$topServiceName, $topServiceCount] = $this->topService($fromDt, $toDt);

        $stats = [];

        $user = auth()->user();
        if ($user?->isAdmin() || $user?->can('reports.view_revenue')) {
            $stats[] = Stat::make('Incasso medio', '€ ' . number_format($avgRevenue, 2, ',', '.'))
                ->description('per appuntamento pagato');
        }

        $stats[] = Stat::make('Clienti unici', $uniqueCustomers);

        $stats[] = Stat::make('Servizio più richiesto', $topServiceName)
            ->description($topServiceCount . ' prenotazioni');

        $stats[] = Stat::make('Appuntamenti in attesa', $pendingCount)
            ->color($pendingCount > 0 ? 'warning' : 'success');

        return $stats;
    }

    private function topService(Carbon $fromDt, Carbon $toDt): array
    {
        $appointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->get(['service_ids']);

        $counts = [];
        foreach ($appointments as $appt) {
            foreach ($appt->service_ids ?? [] as $serviceId) {
                $counts[$serviceId] = ($counts[$serviceId] ?? 0) + 1;
            }
        }

        if (empty($counts)) {
            return ['-', 0];
        }

        arsort($counts);
        $topId    = (int) array_key_first($counts);
        $topCount = $counts[$topId];

        $names = Service::whereIn('id', [$topId])->pluck('name', 'id');
        return [$names[$topId] ?? '-', $topCount];
    }
}
