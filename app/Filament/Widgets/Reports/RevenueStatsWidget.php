<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Payment;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RevenueStatsWidget extends Widget
{
    protected string $view                      = 'filament.widgets.reports.revenue-stats';
    protected static ?int $sort                 = 1;
    protected static bool $isLazy               = false;
    protected int | string | array $columnSpan  = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getStats(): array
    {
        $from = $this->dateFrom ?? now()->startOfMonth()->toDateString();
        $to   = $this->dateTo   ?? now()->endOfMonth()->toDateString();

        $fromDt = \Carbon\Carbon::parse($from)->startOfDay();
        $toDt   = \Carbon\Carbon::parse($to)->endOfDay();

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
            ->join('users', 'users.id', '=', 'appointments.staff_id')
            ->select('appointments.staff_id', 'users.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('appointments.staff_id', 'users.name')
            ->orderByDesc('cnt')
            ->first();

        return [
            'totalRevenue'      => (float) $totalRevenue,
            'totalAppointments' => (int) $totalAppointments,
            'cancellationRate'  => $cancellationRate,
            'topStaffName'      => $topStaffRow?->name ?? '—',
            'topStaffCount'     => (int) ($topStaffRow?->cnt ?? 0),
        ];
    }
}
