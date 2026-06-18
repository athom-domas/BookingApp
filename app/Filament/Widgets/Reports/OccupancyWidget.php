<?php

namespace App\Filament\Widgets\Reports;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\StaffBlockout;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

class OccupancyWidget extends Widget
{
    protected string $view                     = 'filament.widgets.reports.occupancy';
    protected static ?int $sort                = 9;
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

        $rules = AvailabilityRule::where('is_available', true)
            ->get()
            ->groupBy('user_id');

        $blockouts = StaffBlockout::where('start_date', '<=', $to->toDateString())
            ->where('end_date', '>=', $from->toDateString())
            ->get(['user_id', 'start_date', 'end_date']);

        $blockedDays = [];
        foreach ($blockouts as $b) {
            foreach (CarbonPeriod::create($b->start_date, $b->end_date) as $day) {
                $blockedDays[$b->user_id][$day->toDateString()] = true;
            }
        }

        $availableMinutes = 0;
        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $date) {
            $dow     = $date->dayOfWeek;
            $dateStr = $date->toDateString();
            foreach ($rules as $userId => $userRules) {
                if ($blockedDays[$userId][$dateStr] ?? false) continue;
                $dayRule = $userRules->firstWhere('day_of_week', $dow);
                if (! $dayRule) continue;
                $availableMinutes += Carbon::parse($dayRule->start_time)->diffInMinutes(Carbon::parse($dayRule->end_time));
                if ($dayRule->start_time_2 && $dayRule->end_time_2) {
                    $availableMinutes += Carbon::parse($dayRule->start_time_2)->diffInMinutes(Carbon::parse($dayRule->end_time_2));
                }
            }
        }

        $appointments = Appointment::whereBetween('scheduled_date', [$from, $to])
            ->whereNotIn('status', ['cancelled'])
            ->get(['service_ids']);

        $allServiceIds = collect($appointments)
            ->flatMap(fn ($a) => array_map('intval', $a->service_ids ?? []))
            ->unique()->values()->all();

        $durations = Service::whereIn('id', $allServiceIds)
            ->pluck('duration_minutes', 'id')
            ->mapWithKeys(fn ($v, $k) => [(int) $k => (int) $v]);

        $bookedMinutes = 0;
        foreach ($appointments as $appt) {
            foreach ($appt->service_ids ?? [] as $sid) {
                $bookedMinutes += $durations[(int) $sid] ?? 0;
            }
        }

        $rate = $availableMinutes > 0 ? round($bookedMinutes / $availableMinutes * 100, 1) : 0;

        return [
            'rate'           => $rate,
            'bookedHours'    => round($bookedMinutes / 60, 1),
            'availableHours' => round($availableMinutes / 60, 1),
        ];
    }
}
