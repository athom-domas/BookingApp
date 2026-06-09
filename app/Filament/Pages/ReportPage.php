<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Reports\AppointmentsByStatusChartWidget;
use App\Filament\Widgets\Reports\InsightStatsWidget;
use App\Filament\Widgets\Reports\ProductBreakdownChartWidget;
use App\Filament\Widgets\Reports\ProductStatsWidget;
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
    protected static ?int                   $navigationSort  = 3;

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
        $user = auth()->user();
        return ($user?->isAdmin() || ($user?->isStaff() && $user->can('reports.view'))) ?? false;
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
        $user       = auth()->user();
        $hasRevenue = $user?->isAdmin() || ($user?->isStaff() && $user->can('reports.view_revenue'));

        $widgets = [
            InsightStatsWidget::class,
            AppointmentsByStatusChartWidget::class,
            ServiceBreakdownChartWidget::class,
        ];

        if ($hasRevenue) {
            array_unshift($widgets, RevenueStatsWidget::class);
            $widgets[] = RevenueChartWidget::class;
            $widgets[] = StaffPerformanceWidget::class;
            $widgets[] = ProductStatsWidget::class;
            $widgets[] = ProductBreakdownChartWidget::class;
        }

        return $widgets;
    }
}
