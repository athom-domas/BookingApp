<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Payment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class RevenueChartWidget extends ChartWidget
{
    protected ?string $heading = 'Incassi nel tempo';
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    protected function getData(): array
    {
        $from  = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to    = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());
        $byDay = $from->diffInDays($to) <= 31;

        [$displayLabels, $revenueMap] = $byDay
            ? $this->dailyRevenue($from, $to)
            : $this->monthlyRevenue($from, $to);

        $data = array_map(fn ($label) => $revenueMap[$label] ?? 0, $displayLabels);

        return [
            'datasets' => [[
                'label'           => 'Incasso (€)',
                'data'            => $data,
                'borderColor'     => '#2563eb',
                'backgroundColor' => 'rgba(37,99,235,0.1)',
                'fill'            => true,
                'tension'         => 0.3,
            ]],
            'labels' => $displayLabels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function dailyRevenue(Carbon $from, Carbon $to): array
    {
        $rows = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                DB::raw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m-%d') as period"),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        $labels = [];
        $map    = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $key       = $day->format('Y-m-d');
            $display   = $day->format('d/m');
            $labels[]  = $display;
            $map[$display] = isset($rows[$key]) ? (float) $rows[$key] : 0;
        }

        return [$labels, $map];
    }

    private function monthlyRevenue(Carbon $from, Carbon $to): array
    {
        $rows = Payment::completed()
            ->join('appointments', 'appointments.id', '=', 'payments.appointment_id')
            ->whereBetween('appointments.scheduled_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                DB::raw("DATE_FORMAT(appointments.scheduled_date, '%Y-%m') as period"),
                DB::raw('SUM(payments.amount) as total')
            )
            ->groupBy('period')
            ->pluck('total', 'period')
            ->toArray();

        $labels  = [];
        $map     = [];
        $current = $from->copy()->startOfMonth();
        while ($current <= $to) {
            $key       = $current->format('Y-m');
            $display   = $current->translatedFormat('M Y');
            $labels[]  = $display;
            $map[$display] = isset($rows[$key]) ? (float) $rows[$key] : 0;
            $current->addMonth();
        }

        return [$labels, $map];
    }
}
