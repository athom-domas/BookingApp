<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class PeakHoursWidget extends Widget
{
    protected string $view                     = 'filament.widgets.reports.peak-hours';
    protected static ?int $sort                = 11;
    protected static bool $isLazy             = false;
    protected int | string | array $columnSpan = 'full';

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getHeatmap(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to   = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString())->endOfDay();

        $appointments = Appointment::whereBetween('scheduled_date', [$from, $to])
            ->get(['scheduled_date']);

        $matrix = [];
        foreach ($appointments as $appt) {
            $date = Carbon::parse($appt->scheduled_date);
            $dow  = $date->isoWeekday(); // 1=Mon ... 7=Sun
            $hour = (int) $date->format('H');
            $matrix[$dow][$hour] = ($matrix[$dow][$hour] ?? 0) + 1;
        }

        $maxCount = 0;
        foreach ($matrix as $dayData) {
            foreach ($dayData as $cnt) {
                if ($cnt > $maxCount) $maxCount = $cnt;
            }
        }

        return ['matrix' => $matrix, 'maxCount' => $maxCount];
    }
}
