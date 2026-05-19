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
        $fromDt = $from->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        $paidCount = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->count();

        $totalRevenue = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->sum('payments.amount');

        $avgRevenue = $paidCount > 0 ? round((float) $totalRevenue / $paidCount, 2) : 0;

        $uniqueCustomers = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->distinct('user_id')
            ->count('user_id');

        $pendingCount = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'pending')
            ->count();

        [$topServiceName, $topServiceCount] = $this->topService($fromDt, $toDt);

        return [
            Stat::make('Incasso medio', '€ ' . number_format($avgRevenue, 2, ',', '.'))
                ->description('per appuntamento pagato'),

            Stat::make('Clienti unici', $uniqueCustomers),

            Stat::make('Servizio più richiesto', $topServiceName)
                ->description($topServiceCount . ' prenotazioni'),

            Stat::make('Appuntamenti in attesa', $pendingCount)
                ->color($pendingCount > 0 ? 'warning' : 'success'),
        ];
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

        return [Service::find($topId)?->name ?? '-', $topCount];
    }
}
