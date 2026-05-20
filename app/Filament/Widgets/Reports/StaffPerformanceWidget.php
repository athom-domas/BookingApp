<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class StaffPerformanceWidget extends Widget
{
    protected string       $view   = 'filament.widgets.reports.staff-performance';
    protected static bool  $isLazy = false;
    protected static ?int  $sort   = 6;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getRows(): Collection
    {
        $from   = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to     = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $fromDt = $from->copy()->startOfDay();
        $toDt   = $to->copy()->endOfDay();

        $rows = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->join('users', 'users.id', '=', 'appointments.staff_id')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.appointment_id', '=', 'appointments.id')
                     ->where('payments.status', '=', 'completed');
            })
            ->select(
                'appointments.staff_id',
                'users.name',
                DB::raw('COUNT(appointments.id) as total'),
                DB::raw("SUM(CASE WHEN appointments.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled"),
                DB::raw('COALESCE(SUM(payments.amount), 0) as revenue')
            )
            ->groupBy('appointments.staff_id', 'users.name')
            ->orderByDesc('revenue')
            ->get();

        $allAppointments = Appointment::whereBetween('scheduled_date', [$fromDt, $toDt])
            ->get(['staff_id', 'service_ids']);

        $countsByStaff = [];
        foreach ($allAppointments as $appt) {
            foreach ($appt->service_ids ?? [] as $sid) {
                $countsByStaff[$appt->staff_id][$sid] = ($countsByStaff[$appt->staff_id][$sid] ?? 0) + 1;
            }
        }

        $allServiceIds = collect($countsByStaff)
            ->flatMap(fn ($c) => array_keys($c))
            ->unique()->values()->all();
        $serviceNames = Service::whereIn('id', $allServiceIds)->pluck('name', 'id');

        return $rows->map(function ($row) use ($countsByStaff, $serviceNames) {
            $staffCounts = $countsByStaff[$row->staff_id] ?? [];
            arsort($staffCounts);
            $topId               = (int) (array_key_first($staffCounts) ?? 0);
            $row->top_service        = $topId ? ($serviceNames[$topId] ?? '-') : '-';
            $row->cancellation_rate  = $row->total > 0
                ? round((int) $row->cancelled / (int) $row->total * 100, 1)
                : 0;
            return $row;
        });
    }
}
