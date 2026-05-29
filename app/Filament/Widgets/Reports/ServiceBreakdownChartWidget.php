<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ServiceBreakdownChartWidget extends ChartWidget
{
    protected ?string $heading      = 'Appuntamenti per servizio';
    protected static bool $isLazy   = false;
    protected static ?int $sort     = 5;
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

        $appointments = Appointment::whereBetween('scheduled_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->get(['service_ids']);

        $counts = [];
        foreach ($appointments as $appt) {
            foreach ($appt->service_ids ?? [] as $serviceId) {
                $counts[$serviceId] = ($counts[$serviceId] ?? 0) + 1;
            }
        }

        arsort($counts);

        $serviceIds   = array_keys($counts);
        $serviceNames = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

        $labels = array_map(fn ($id) => $serviceNames[$id] ?? "Servizio #$id", $serviceIds);
        $data   = array_values($counts);

        $palette = [
            'rgba(99,102,241,0.85)',
            'rgba(244,63,94,0.85)',
            'rgba(16,185,129,0.85)',
            'rgba(245,158,11,0.85)',
            'rgba(139,92,246,0.85)',
            'rgba(14,165,233,0.85)',
            'rgba(251,146,60,0.85)',
        ];
        $bgColors = array_map(fn ($i) => $palette[$i % count($palette)], range(0, max(0, count($data) - 1)));

        return [
            'datasets' => [[
                'data'            => $data,
                'backgroundColor' => $bgColors,
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
                'legend' => ['position' => 'right', 'labels' => ['boxWidth' => 10, 'padding' => 10]],
            ],
        ];
    }
}
