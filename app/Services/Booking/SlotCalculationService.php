<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SlotCalculationService
{
    /**
     * @param array{
     *   date: Carbon|string,
     *   serviceIds: int[],
     *   staffId?: int,
     *   staffPreference?: 'specific'|'any'
     * } $params
     */
    public function getAvailableSlots(array $params): array
    {
        $date            = Carbon::parse($params['date'])->startOfDay();
        $serviceIds      = $params['serviceIds'] ?? [];
        $staffId         = $params['staffId'] ?? null;
        $staffPreference = $params['staffPreference'] ?? 'specific';

        if (empty($serviceIds)) {
            return [];
        }

        $totalDuration = $this->calculateTotalDuration($serviceIds);
        if ($totalDuration <= 0) {
            return [];
        }

        $eligibleStaff = $this->getEligibleOperators($serviceIds, $staffId, $staffPreference);
        if ($eligibleStaff->isEmpty()) {
            return [];
        }

        $granularity     = SystemSetting::getSlotGranularity();
        $slotsByOperator = [];
        foreach ($eligibleStaff as $staff) {
            $slots = $this->getSlotsForOperator($staff, $date, $totalDuration, $granularity);
            if (! empty($slots)) {
                $slotsByOperator[$staff->id] = $slots;
            }
        }

        if (empty($slotsByOperator)) {
            return [];
        }

        if ($staffPreference === 'specific' && $staffId) {
            return $slotsByOperator[$staffId] ?? [];
        }

        return $this->groupSlotsByTime($slotsByOperator);
    }

    public function calculateTotalDuration(array $serviceIds): int
    {
        if (empty($serviceIds)) {
            return 0;
        }

        return (int) Service::whereIn('id', $serviceIds)->active()->sum('duration_minutes');
    }

    public function getEligibleOperators(
        array $serviceIds,
        ?int $specificStaffId = null,
        string $preference = 'any'
    ): Collection {
        $query = User::whereHas('roles', fn ($q) => $q->where('name', 'staff'))
            ->where('business_id', app('current_business_id'))
            ->when(
                $preference === 'specific' && $specificStaffId,
                fn ($q) => $q->where('id', $specificStaffId)
            );

        // Staff must offer ALL requested services
        foreach ($serviceIds as $serviceId) {
            $query->whereHas('services', fn ($q) => $q->where('services.id', $serviceId));
        }

        return $query->get();
    }

    public function getWorkRangesForOperator(User $staff, Carbon $date): array
    {
        return $this->getWorkRanges($staff, $date);
    }

    public function getOccupationsForOperator(User $staff, Carbon $date): array
    {
        return $this->getOccupations($staff, $date);
    }

    public function getFreeRangesForOperator(User $staff, Carbon $date): array
    {
        $workRanges  = $this->getWorkRanges($staff, $date);
        $occupations = $this->getOccupations($staff, $date);

        return $this->calculateFreeRanges($workRanges, $occupations);
    }

    public function isSlotFree(array $params): bool
    {
        $date            = Carbon::parse($params['date'])->startOfDay();
        $slotStart       = Carbon::parse($params['slotStart']);
        $serviceIds      = $params['serviceIds'];
        $staffId         = $params['staffId'] ?? null;
        $staffPreference = $params['staffPreference'] ?? 'specific';

        $duration = $this->calculateTotalDuration($serviceIds);
        if ($duration <= 0) {
            return false;
        }

        $slotEnd       = $slotStart->copy()->addMinutes($duration);
        $eligibleStaff = $this->getEligibleOperators($serviceIds, $staffId, $staffPreference);

        foreach ($eligibleStaff as $staff) {
            $freeRanges = $this->calculateFreeRanges(
                $this->getWorkRanges($staff, $date),
                $this->getOccupations($staff, $date)
            );

            foreach ($freeRanges as $range) {
                if ($range['start']->lte($slotStart) && $range['end']->gte($slotEnd)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function getAvailableOperatorsForSlot(array $params): array
    {
        $date       = Carbon::parse($params['date'])->startOfDay();
        $slotStart  = Carbon::parse($params['slotStart']);
        $serviceIds = $params['serviceIds'];

        $duration      = $this->calculateTotalDuration($serviceIds);
        $slotEnd       = $slotStart->copy()->addMinutes($duration);
        $eligibleStaff = $this->getEligibleOperators($serviceIds, null, 'any');
        $available     = [];

        foreach ($eligibleStaff as $staff) {
            $freeRanges = $this->calculateFreeRanges(
                $this->getWorkRanges($staff, $date),
                $this->getOccupations($staff, $date)
            );

            foreach ($freeRanges as $range) {
                if ($range['start']->lte($slotStart) && $range['end']->gte($slotEnd)) {
                    $available[] = $staff->id;
                    break;
                }
            }
        }

        return $available;
    }

    private function getSlotsForOperator(User $staff, Carbon $date, int $duration, int $granularity): array
    {
        $workRanges = $this->getWorkRanges($staff, $date);
        if (empty($workRanges)) {
            return [];
        }

        $occupations = $this->getOccupations($staff, $date);
        $freeRanges  = $this->calculateFreeRanges($workRanges, $occupations);

        if ($date->isToday()) {
            $now = Carbon::now();
            // Round up to the next granularity boundary so slots stay on the configured grid
            // e.g. now=15:43 → cutoff=15:45, now=15:45:01 → cutoff=16:00
            $totalMins = $now->hour * 60 + $now->minute + ($now->second > 0 ? 1 : 0);
            $cutoff    = $date->copy()->addMinutes((int) ceil($totalMins / $granularity) * $granularity);

            $freeRanges = array_values(array_filter(
                array_map(function (array $range) use ($cutoff): ?array {
                    if ($range['end'] <= $cutoff) {
                        return null;
                    }
                    if ($range['start'] < $cutoff) {
                        $range['start'] = $cutoff->copy();
                    }

                    return $range;
                }, $freeRanges)
            ));
        }

        $slots = [];
        foreach ($freeRanges as $freeRange) {
            $slots = array_merge($slots, $this->generateSlotsFromRange($freeRange, $duration, $granularity));
        }

        return $slots;
    }

    private function getWorkRanges(User $staff, Carbon $date): array
    {
        // Full-day blockout: start_time IS NULL — staff completamente bloccato
        $hasFullDayBlockout = StaffBlockout::where('user_id', $staff->id)
            ->where('business_id', $staff->business_id)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->whereNull('start_time')
            ->exists();

        if ($hasFullDayBlockout) {
            return [];
        }

        $rules = AvailabilityRule::where('user_id', $staff->id)
            ->where('day_of_week', $date->dayOfWeek)
            ->where('is_available', true)
            ->get();

        $ranges = [];
        foreach ($rules as $rule) {
            $ranges[] = [
                'start' => $date->copy()->setTimeFromTimeString($rule->start_time),
                'end'   => $date->copy()->setTimeFromTimeString($rule->end_time),
            ];
            if ($rule->start_time_2 && $rule->end_time_2) {
                $ranges[] = [
                    'start' => $date->copy()->setTimeFromTimeString($rule->start_time_2),
                    'end'   => $date->copy()->setTimeFromTimeString($rule->end_time_2),
                ];
            }
        }

        // Time-range blockouts: sottrai le fasce orarie bloccate
        $timeBlockouts = StaffBlockout::where('user_id', $staff->id)
            ->where('business_id', $staff->business_id)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();

        foreach ($timeBlockouts as $blockout) {
            $blockStart = $date->copy()->setTimeFromTimeString($blockout->start_time);
            $blockEnd   = $date->copy()->setTimeFromTimeString($blockout->end_time);
            $ranges     = $this->subtractRange($ranges, $blockStart, $blockEnd);
        }

        return $ranges;
    }

    private function subtractRange(array $ranges, Carbon $blockStart, Carbon $blockEnd): array
    {
        $result = [];
        foreach ($ranges as $range) {
            if ($blockEnd <= $range['start'] || $blockStart >= $range['end']) {
                $result[] = $range;
            } elseif ($blockStart <= $range['start'] && $blockEnd >= $range['end']) {
                // blockout copre tutto il range — droppato
            } elseif ($blockStart > $range['start'] && $blockEnd < $range['end']) {
                // blockout in mezzo — split
                $result[] = ['start' => $range['start']->copy(), 'end' => $blockStart->copy()];
                $result[] = ['start' => $blockEnd->copy(),       'end' => $range['end']->copy()];
            } elseif ($blockStart <= $range['start']) {
                $result[] = ['start' => $blockEnd->copy(), 'end' => $range['end']->copy()];
            } else {
                $result[] = ['start' => $range['start']->copy(), 'end' => $blockStart->copy()];
            }
        }
        return $result;
    }

    private function getOccupations(User $staff, Carbon $date): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();
        $result   = [];

        $appointments = Appointment::where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('scheduled_date', [$dayStart, $dayEnd])
            ->get();

        $allServiceIds = $appointments
            ->flatMap(fn ($a) => $a->service_ids ?? ($a->service_id ? [$a->service_id] : []))
            ->unique()
            ->values()
            ->all();

        $durations = Service::whereIn('id', $allServiceIds)->pluck('duration_minutes', 'id');

        foreach ($appointments as $appt) {
            $sids     = $appt->service_ids ?? ($appt->service_id ? [$appt->service_id] : []);
            $duration = collect($sids)->sum(fn ($id) => $durations[$id] ?? 0);
            if ($duration <= 0) {
                continue;
            }
            $result[] = [
                'start' => $appt->scheduled_date,
                'end'   => $appt->scheduled_date->copy()->addMinutes($duration),
                'type'  => 'appointment',
            ];
        }

        return $result;
    }

    /**
     * Subtracts occupations from work ranges to produce free time ranges.
     *
     * Example:
     *   Work: 09:00-13:00
     *   Busy: 09:00-09:30, 10:00-10:30
     *   Free: 09:30-10:00, 10:30-13:00
     */
    private function calculateFreeRanges(array $workRanges, array $occupations): array
    {
        $freeRanges = [];

        foreach ($workRanges as $workRange) {
            $current = $workRange['start']->copy();
            $end     = $workRange['end'];

            $sorted = collect($occupations)
                ->sortBy(fn ($occ) => $occ['start']->timestamp)
                ->values();

            foreach ($sorted as $occupation) {
                if ($occupation['end'] <= $current || $occupation['start'] >= $end) {
                    continue;
                }

                if ($occupation['start'] > $current) {
                    $freeRanges[] = ['start' => $current->copy(), 'end' => $occupation['start']->copy()];
                }

                if ($occupation['end'] > $current) {
                    $current = $occupation['end']->copy();
                }
            }

            if ($current < $end) {
                $freeRanges[] = ['start' => $current->copy(), 'end' => $end->copy()];
            }
        }

        return $freeRanges;
    }

    /**
     * Generates start times within a free range using configured granularity.
     *
     * Slots count = floor((range_minutes - duration) / granularity) + 1
     */
    private function generateSlotsFromRange(array $range, int $duration, int $granularity): array
    {
        $start = $range['start'];
        $end   = $range['end'];
        $rangeMin    = $start->diffInMinutes($end);

        if ($rangeMin < $duration) {
            return [];
        }

        $numSlots = (int) floor(($rangeMin - $duration) / $granularity) + 1;
        $slots    = [];

        for ($i = 0; $i < $numSlots; $i++) {
            $slotStart = $start->copy()->addMinutes($i * $granularity);
            $slotEnd   = $slotStart->copy()->addMinutes($duration);

            if ($slotEnd > $end) {
                break;
            }

            $slots[] = [
                'start'         => $slotStart->format('H:i'),
                'end'           => $slotEnd->format('H:i'),
                'startDateTime' => $slotStart,
                'endDateTime'   => $slotEnd,
                'timestamp'     => $slotStart->timestamp,
            ];
        }

        return $slots;
    }

    private function groupSlotsByTime(array $slotsByOperator): array
    {
        $grouped = [];

        foreach ($slotsByOperator as $staffId => $slots) {
            foreach ($slots as $slot) {
                $key = $slot['timestamp'];

                if (! isset($grouped[$key])) {
                    $grouped[$key] = [
                        'start'              => $slot['start'],
                        'end'                => $slot['end'],
                        'availableOperators' => [],
                    ];
                }

                $grouped[$key]['availableOperators'][] = $staffId;
            }
        }

        ksort($grouped);

        return array_values($grouped);
    }

    // ── Monthly context approach ──────────────────────────────────────────────

    /**
     * Returns available dates for a month using a single pre-loaded context
     * instead of re-querying DB for each day.
     *
     * @param array{month: string, serviceIds: int[], staffId?: int|null} $params
     */
    public function getAvailableDatesForMonth(array $params): array
    {
        $serviceIds = $params['serviceIds'];
        $staffId    = $params['staffId'] ?? null;
        $preference = $staffId ? 'specific' : 'any';

        $start   = Carbon::createFromFormat('Y-m', $params['month'])->startOfMonth();
        $end     = $start->copy()->endOfMonth();
        $today   = Carbon::today();
        $maxDate = $today->copy()->addDays(SystemSetting::getBookingMaxDaysAhead());

        $ctx = $this->buildMonthlyContext($serviceIds, $staffId, $preference, $start, $end);

        if ($ctx['eligibleStaff']->isEmpty() || $ctx['totalDuration'] <= 0) {
            return [];
        }

        $available = [];
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->lt($today) || $day->gt($maxDate)) {
                continue;
            }

            if ($this->hasAvailableSlotsInContext($day, $ctx)) {
                $available[] = $day->toDateString();
            }
        }

        return $available;
    }

    private function buildMonthlyContext(
        array $serviceIds,
        ?int $staffId,
        string $preference,
        Carbon $monthStart,
        Carbon $monthEnd
    ): array {
        $eligibleStaff = $this->getEligibleOperators($serviceIds, $staffId, $preference);
        $totalDuration = $this->calculateTotalDuration($serviceIds);
        $granularity   = SystemSetting::getSlotGranularity();

        if ($eligibleStaff->isEmpty() || $totalDuration <= 0) {
            return [
                'eligibleStaff'           => $eligibleStaff,
                'totalDuration'           => $totalDuration,
                'granularity'             => $granularity,
                'rulesByUserId'           => collect(),
                'blockoutsByUserId'       => collect(),
                'appointmentsByStaffDate' => collect(),
                'serviceDurations'        => [],
            ];
        }

        $staffIds = $eligibleStaff->pluck('id')->all();

        $rulesByUserId = AvailabilityRule::whereIn('user_id', $staffIds)
            ->where('is_available', true)
            ->get()
            ->groupBy('user_id');

        $blockoutsByUserId = StaffBlockout::whereIn('user_id', $staffIds)
            ->where('start_date', '<=', $monthEnd->toDateString())
            ->where('end_date', '>=', $monthStart->toDateString())
            ->get()
            ->groupBy('user_id');

        $allAppointments = Appointment::whereIn('staff_id', $staffIds)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('scheduled_date', [
                $monthStart->copy()->startOfDay(),
                $monthEnd->copy()->endOfDay(),
            ])
            ->get();

        $appointmentsByStaffDate = $allAppointments->groupBy(
            fn ($a) => $a->staff_id . '|' . $a->scheduled_date->toDateString()
        );

        $apptServiceIds = $allAppointments
            ->flatMap(fn ($a) => $a->service_ids ?? [])
            ->unique()
            ->values()
            ->all();

        $serviceDurations = empty($apptServiceIds)
            ? []
            : Service::whereIn('id', $apptServiceIds)->pluck('duration_minutes', 'id')->all();

        return compact(
            'eligibleStaff', 'totalDuration', 'granularity',
            'rulesByUserId', 'blockoutsByUserId',
            'appointmentsByStaffDate', 'serviceDurations'
        );
    }

    private function hasAvailableSlotsInContext(Carbon $day, array $ctx): bool
    {
        $dayStr = $day->toDateString();

        foreach ($ctx['eligibleStaff'] as $staff) {
            $workRanges = $this->getWorkRangesFromContext($staff, $day, $dayStr, $ctx);
            if (empty($workRanges)) {
                continue;
            }

            $appts       = $ctx['appointmentsByStaffDate']->get($staff->id . '|' . $dayStr, collect());
            $occupations = $this->getOccupationsFromData($appts, $ctx['serviceDurations']);
            $freeRanges  = $this->calculateFreeRanges($workRanges, $occupations);

            if ($day->isToday()) {
                $now       = Carbon::now();
                $totalMins = $now->hour * 60 + $now->minute + ($now->second > 0 ? 1 : 0);
                $cutoff    = $day->copy()->addMinutes((int) ceil($totalMins / $ctx['granularity']) * $ctx['granularity']);

                $freeRanges = array_values(array_filter(
                    array_map(function (array $range) use ($cutoff): ?array {
                        if ($range['end'] <= $cutoff) {
                            return null;
                        }
                        if ($range['start'] < $cutoff) {
                            $range['start'] = $cutoff->copy();
                        }
                        return $range;
                    }, $freeRanges)
                ));
            }

            foreach ($freeRanges as $range) {
                if ($range['start']->diffInMinutes($range['end']) >= $ctx['totalDuration']) {
                    return true;
                }
            }
        }

        return false;
    }

    private function getWorkRangesFromContext(User $staff, Carbon $day, string $dayStr, array $ctx): array
    {
        $staffBlockouts = $ctx['blockoutsByUserId']->get($staff->id, collect());

        $hasFullDayBlockout = $staffBlockouts->contains(function ($b) use ($dayStr) {
            return $b->start_date->toDateString() <= $dayStr
                && $b->end_date->toDateString() >= $dayStr
                && $b->start_time === null;
        });

        if ($hasFullDayBlockout) {
            return [];
        }

        $dow   = $day->dayOfWeek;
        $rules = $ctx['rulesByUserId']->get($staff->id, collect())->where('day_of_week', $dow);

        $ranges = [];
        foreach ($rules as $rule) {
            $ranges[] = [
                'start' => $day->copy()->setTimeFromTimeString($rule->start_time),
                'end'   => $day->copy()->setTimeFromTimeString($rule->end_time),
            ];
            if ($rule->start_time_2 && $rule->end_time_2) {
                $ranges[] = [
                    'start' => $day->copy()->setTimeFromTimeString($rule->start_time_2),
                    'end'   => $day->copy()->setTimeFromTimeString($rule->end_time_2),
                ];
            }
        }

        $timeBlockouts = $staffBlockouts->filter(function ($b) use ($dayStr) {
            return $b->start_date->toDateString() <= $dayStr
                && $b->end_date->toDateString() >= $dayStr
                && $b->start_time !== null
                && $b->end_time !== null;
        });

        foreach ($timeBlockouts as $blockout) {
            $blockStart = $day->copy()->setTimeFromTimeString($blockout->start_time);
            $blockEnd   = $day->copy()->setTimeFromTimeString($blockout->end_time);
            $ranges     = $this->subtractRange($ranges, $blockStart, $blockEnd);
        }

        return $ranges;
    }

    private function getOccupationsFromData(Collection $appointments, array $serviceDurations): array
    {
        $result = [];
        foreach ($appointments as $appt) {
            $sids     = $appt->service_ids ?? ($appt->service_id ? [$appt->service_id] : []);
            $duration = collect($sids)->sum(fn ($id) => $serviceDurations[$id] ?? 0);
            if ($duration <= 0) {
                continue;
            }
            $result[] = [
                'start' => $appt->scheduled_date,
                'end'   => $appt->scheduled_date->copy()->addMinutes($duration),
                'type'  => 'appointment',
            ];
        }

        return $result;
    }
}
