<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use Carbon\Carbon;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class CustomerRetentionWidget extends Widget
{
    protected string $view                     = 'filament.widgets.reports.customer-retention';
    protected static ?int $sort                = 10;
    protected static bool $isLazy             = false;
    protected int | string | array $columnSpan = 1;

    public ?string $dateFrom = null;
    public ?string $dateTo   = null;

    #[On('reportFiltersUpdated')]
    public function updateFilters(string $dateFrom, string $dateTo): void
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo   = $dateTo;
    }

    public function getStats(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->startOfMonth()->toDateString())->startOfDay();
        $to   = Carbon::parse($this->dateTo   ?? now()->endOfMonth()->toDateString())->endOfDay();

        $customerIds = Appointment::whereBetween('scheduled_date', [$from, $to])
            ->distinct('user_id')
            ->pluck('user_id');

        $total = $customerIds->count();

        if ($total === 0) {
            return [
                'total'          => 0,
                'new'            => 0,
                'returning'      => 0,
                'newPct'         => 0,
                'returningPct'   => 0,
                'avgReturnWeeks' => null,
            ];
        }

        $newCount = Appointment::whereIn('user_id', $customerIds)
            ->selectRaw('user_id, MIN(scheduled_date) as first_appt')
            ->groupBy('user_id')
            ->get()
            ->filter(fn ($row) => Carbon::parse($row->first_appt)->gte($from))
            ->count();

        $returning = $total - $newCount;

        $allAppts = Appointment::whereIn('user_id', $customerIds)
            ->whereIn('status', ['confirmed', 'completed'])
            ->orderBy('user_id')
            ->orderBy('scheduled_date')
            ->get(['user_id', 'scheduled_date'])
            ->groupBy('user_id');

        $gaps = [];
        foreach ($allAppts as $userAppts) {
            if ($userAppts->count() < 2) continue;
            $sorted = $userAppts->sortBy('scheduled_date')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                $gaps[] = Carbon::parse($sorted[$i - 1]->scheduled_date)
                    ->diffInDays(Carbon::parse($sorted[$i]->scheduled_date));
            }
        }

        $avgReturnWeeks = count($gaps) > 0
            ? round(array_sum($gaps) / count($gaps) / 7, 1)
            : null;

        return [
            'total'          => $total,
            'new'            => $newCount,
            'returning'      => $returning,
            'newPct'         => round($newCount / $total * 100),
            'returningPct'   => round($returning / $total * 100),
            'avgReturnWeeks' => $avgReturnWeeks,
        ];
    }
}
