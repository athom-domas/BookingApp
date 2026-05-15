<?php

namespace App\Services\Booking;

use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AvailabilityRule;
use App\Models\Service;
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
        $excludeHoldId   = $params['excludeHoldId'] ?? null;

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

        $slotsByOperator = [];
        foreach ($eligibleStaff as $staff) {
            $slots = $this->getSlotsForOperator($staff, $date, $totalDuration, $excludeHoldId);
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

    private function getSlotsForOperator(User $staff, Carbon $date, int $duration, ?int $excludeHoldId = null): array
    {
        $workRanges = $this->getWorkRanges($staff, $date);
        if (empty($workRanges)) {
            return [];
        }

        $occupations = $this->getOccupations($staff, $date, $excludeHoldId);
        $freeRanges  = $this->calculateFreeRanges($workRanges, $occupations);

        $slots = [];
        foreach ($freeRanges as $freeRange) {
            $slots = array_merge($slots, $this->generateSlotsFromRange($freeRange, $duration));
        }

        return $slots;
    }

    private function getWorkRanges(User $staff, Carbon $date): array
    {
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

        return $ranges;
    }

    private function getOccupations(User $staff, Carbon $date, ?int $excludeHoldId = null): array
    {
        $dayStart = $date->copy()->startOfDay();
        $dayEnd   = $date->copy()->endOfDay();
        $result   = [];

        $appointments = Appointment::with('service')
            ->where('staff_id', $staff->id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereBetween('scheduled_date', [$dayStart, $dayEnd])
            ->get();

        foreach ($appointments as $appt) {
            $duration = $appt->service?->duration_minutes ?? 0;
            if ($duration <= 0) {
                continue;
            }
            $result[] = [
                'start' => $appt->scheduled_date,
                'end'   => $appt->scheduled_date->copy()->addMinutes($duration),
                'type'  => 'appointment',
            ];
        }

        $holdsQuery = AppointmentHold::active()
            ->where('staff_id', $staff->id)
            ->whereBetween('starts_at', [$dayStart, $dayEnd]);

        if ($excludeHoldId !== null) {
            $holdsQuery->where('id', '!=', $excludeHoldId);
        }

        foreach ($holdsQuery->get() as $hold) {
            $result[] = [
                'start' => $hold->starts_at,
                'end'   => $hold->ends_at,
                'type'  => 'hold',
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
    private function generateSlotsFromRange(array $range, int $duration): array
    {
        $granularity = SystemSetting::getSlotGranularity();
        $start       = $range['start'];
        $end         = $range['end'];
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
}
