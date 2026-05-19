<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RevenueStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort      = 1;
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
        $from = $this->dateFrom ?? now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?? now()->endOfMonth()->toDateString();

        $fromDt = $from . ' 00:00:00';
        $toDt   = $to   . ' 23:59:59';

        $totalRevenue = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$fromDt, $toDt])
            ->sum('payments.amount');

        $totalAppointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])->count();

        $cancelledCount = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'cancelled')
            ->count();

        $cancellationRate = $totalAppointments > 0
            ? round($cancelledCount / $totalAppointments * 100, 1)
            : 0;

        $topStaffRow = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->where('status', 'completed')
            ->select('staff_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('staff_id')
            ->orderByDesc('cnt')
            ->first();

        $topStaffName  = $topStaffRow ? (User::find($topStaffRow->staff_id)?->name ?? '-') : '-';
        $topStaffCount = $topStaffRow?->cnt ?? 0;

        return [
            Stat::make('Incasso totale', '€ ' . number_format((float) $totalRevenue, 2, ',', '.')),

            Stat::make('Appuntamenti', $totalAppointments),

            Stat::make('Tasso cancellazione', $cancellationRate . '%')
                ->color($cancellationRate > 20 ? 'danger' : 'success'),

            Stat::make('Staff più produttivo', $topStaffName)
                ->description($topStaffCount . ' appuntamenti completati'),
        ];
    }
}
