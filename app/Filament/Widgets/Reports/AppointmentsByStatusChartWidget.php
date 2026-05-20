<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AppointmentsByStatusChartWidget extends ChartWidget
{
    protected ?string $heading    = 'Appuntamenti per stato';
    protected static bool $isLazy = false;
    protected static ?int $sort   = 4;
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

        $dbKeys = $byDay ? $this->dailyKeys($from, $to) : $this->monthlyKeys($from, $to);
        $format = $byDay ? '%Y-%m-%d' : '%Y-%m';

        $dateExpr = DB::connection()->getDriverName() === 'sqlite'
            ? DB::raw("strftime('{$format}', scheduled_date) as period")
            : DB::raw("DATE_FORMAT(scheduled_date, '{$format}') as period");

        $rows = Appointment::whereBetween('scheduled_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select(
                $dateExpr,
                'status',
                DB::raw('COUNT(*) as cnt')
            )
            ->groupBy('period', 'status')
            ->get();

        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $colors   = [
            'pending'   => 'rgba(234,179,8,0.7)',
            'confirmed' => 'rgba(59,130,246,0.7)',
            'completed' => 'rgba(34,197,94,0.7)',
            'cancelled' => 'rgba(239,68,68,0.7)',
        ];
        $italianLabels = [
            'pending'   => 'In attesa',
            'confirmed' => 'Confermati',
            'completed' => 'Completati',
            'cancelled' => 'Cancellati',
        ];

        $datasets = [];
        foreach ($statuses as $status) {
            $map = $rows->where('status', $status)->pluck('cnt', 'period')->toArray();
            $datasets[] = [
                'label'           => $italianLabels[$status],
                'data'            => array_map(fn ($dbKey) => (int) ($map[$dbKey] ?? 0), array_keys($dbKeys)),
                'backgroundColor' => $colors[$status],
            ];
        }

        return [
            'datasets' => $datasets,
            'labels'   => array_values($dbKeys),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    private function dailyKeys(Carbon $from, Carbon $to): array
    {
        $keys = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $keys[$day->format('Y-m-d')] = $day->format('d/m');
        }
        return $keys;
    }

    private function monthlyKeys(Carbon $from, Carbon $to): array
    {
        $keys    = [];
        $current = $from->copy()->startOfMonth();
        while ($current <= $to) {
            $keys[$current->format('Y-m')] = $current->translatedFormat('M Y');
            $current->addMonth();
        }
        return $keys;
    }
}
