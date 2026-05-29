<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;

class AppointmentsByStatusChartWidget extends ChartWidget
{
    protected ?string $heading      = 'Appuntamenti per stato';
    protected static bool $isLazy   = false;
    protected static ?int $sort     = 4;
    protected int | string | array $columnSpan = 1;

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
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString());
        $to   = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString());

        $statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
        $colors   = ['#f59e0b', '#6366f1', '#10b981', '#f43f5e'];
        $labels   = ['In attesa', 'Confermati', 'Completati', 'Cancellati'];

        $counts = Appointment::whereBetween('scheduled_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->toArray();

        return [
            'datasets' => [[
                'data'            => array_map(fn ($s) => (int) ($counts[$s] ?? 0), $statuses),
                'backgroundColor' => $colors,
                'borderWidth'     => 0,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'cutout'  => '60%',
            'plugins' => [
                'legend' => ['position' => 'bottom', 'labels' => ['boxWidth' => 10, 'padding' => 12]],
            ],
        ];
    }
}
