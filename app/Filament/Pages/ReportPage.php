<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Reports\AppointmentsByStatusChartWidget;
use App\Filament\Widgets\Reports\InsightStatsWidget;
use App\Filament\Widgets\Reports\RevenueChartWidget;
use App\Filament\Widgets\Reports\RevenueStatsWidget;
use App\Filament\Widgets\Reports\ServiceBreakdownChartWidget;
use App\Filament\Widgets\Reports\StaffPerformanceWidget;
use Filament\Pages\Page;

class ReportPage extends Page
{
    protected static ?string                 $slug            = 'report';
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string                $navigationLabel = 'Report';
    protected static ?string                $title           = 'Report';
    protected string                        $view            = 'filament.pages.report';
    protected static ?int                   $navigationSort  = 10;

    public string $period   = 'month';
    public string $dateFrom = '';
    public string $dateTo   = '';

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo   = now()->endOfMonth()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function setPeriod(string $period): void
    {
        $this->period = $period;
        [$this->dateFrom, $this->dateTo] = match ($period) {
            'today' => [today()->toDateString(), today()->toDateString()],
            'week'  => [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()],
            'month' => [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()],
            'year'  => [now()->startOfYear()->toDateString(), now()->endOfYear()->toDateString()],
            default => [$this->dateFrom, $this->dateTo],
        };
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function updatedDateFrom(): void
    {
        $this->period = 'custom';
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function updatedDateTo(): void
    {
        $this->period = 'custom';
        $this->dispatch('reportFiltersUpdated', dateFrom: $this->dateFrom, dateTo: $this->dateTo);
    }

    public function getWidgetData(): array
    {
        return ['dateFrom' => $this->dateFrom, 'dateTo' => $this->dateTo];
    }

    public function getWidgets(): array
    {
        return array_filter([
            class_exists(RevenueStatsWidget::class) ? RevenueStatsWidget::class : null,
            class_exists(InsightStatsWidget::class) ? InsightStatsWidget::class : null,
            class_exists(RevenueChartWidget::class) ? RevenueChartWidget::class : null,
            class_exists(AppointmentsByStatusChartWidget::class) ? AppointmentsByStatusChartWidget::class : null,
            class_exists(ServiceBreakdownChartWidget::class) ? ServiceBreakdownChartWidget::class : null,
            class_exists(StaffPerformanceWidget::class) ? StaffPerformanceWidget::class : null,
        ]);
    }
}
