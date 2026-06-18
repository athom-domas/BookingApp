<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Reports\AppointmentsByStatusChartWidget;
use App\Filament\Widgets\Reports\CustomerRetentionWidget;
use App\Filament\Widgets\Reports\InsightStatsWidget;
use App\Filament\Widgets\Reports\OccupancyWidget;
use App\Filament\Widgets\Reports\PeakHoursWidget;
use App\Filament\Widgets\Reports\ProductBreakdownChartWidget;
use App\Filament\Widgets\Reports\ProductStatsWidget;
use App\Filament\Widgets\Reports\RevenueChartWidget;
use App\Filament\Widgets\Reports\RevenueStatsWidget;
use App\Filament\Widgets\Reports\ServiceBreakdownChartWidget;
use App\Filament\Widgets\Reports\StaffPerformanceWidget;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\Carbon;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            OccupancyWidget::class,
            CustomerRetentionWidget::class,
            AppointmentsByStatusChartWidget::class,
            ServiceBreakdownChartWidget::class,
            PeakHoursWidget::class,
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

    public function exportCsv(): StreamedResponse
    {
        $from = Carbon::parse($this->dateFrom)->startOfDay();
        $to   = Carbon::parse($this->dateTo)->endOfDay();

        $appointments = Appointment::with(['user', 'staff', 'payment'])
            ->whereBetween('scheduled_date', [$from, $to])
            ->orderBy('scheduled_date')
            ->get();

        $serviceIds = $appointments->flatMap(fn ($a) => $a->service_ids ?? [])->unique()->values()->all();
        $services   = Service::whereIn('id', $serviceIds)->pluck('name', 'id');

        $filename = 'appuntamenti-' . $this->dateFrom . '-' . $this->dateTo . '.csv';

        return response()->streamDownload(function () use ($appointments, $services) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Data', 'Ora', 'Cliente', 'Staff', 'Servizi', 'Stato', 'Importo (€)', 'Pagato'], ';');

            $statusMap = [
                'pending'   => 'In attesa',
                'confirmed' => 'Confermato',
                'completed' => 'Completato',
                'cancelled' => 'Disdetto',
            ];

            foreach ($appointments as $appt) {
                $serviceNames = collect($appt->service_ids ?? [])
                    ->map(fn ($id) => $services[$id] ?? "Servizio #$id")
                    ->implode(', ');

                $paid   = $appt->payment?->status === 'completed';
                $amount = $paid
                    ? number_format((float) $appt->payment->amount, 2, ',', '.')
                    : number_format((float) $appt->final_price, 2, ',', '.');

                fputcsv($handle, [
                    $appt->scheduled_date->format('d/m/Y'),
                    $appt->scheduled_date->format('H:i'),
                    $appt->user?->name ?? '—',
                    $appt->staff?->name ?? '—',
                    $serviceNames,
                    $statusMap[$appt->status] ?? $appt->status,
                    $amount,
                    $paid ? 'Sì' : 'No',
                ], ';');
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
