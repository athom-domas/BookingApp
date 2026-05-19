<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\On;

class ServiceBreakdownChartWidget extends ChartWidget
{
    protected ?string $heading   = 'Appuntamenti per servizio';
    protected static ?int $sort  = 5;
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

        return [
            'datasets' => [[
                'label'           => 'Appuntamenti',
                'data'            => $data,
                'backgroundColor' => 'rgba(37,99,235,0.7)',
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins'   => ['legend' => ['display' => false]],
        ];
    }
}
