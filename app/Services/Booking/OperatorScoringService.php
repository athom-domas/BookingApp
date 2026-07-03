<?php

namespace App\Services\Booking;

use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;

class OperatorScoringService
{
    public function __construct(private readonly SlotCalculationService $slots) {}

    /**
     * Picks the best operator for a slot. Score: lower = better.
     *
     * Criteria (in priority order):
     * 1. Perfect fit (slot fills remaining free range exactly)
     * 2. Avoids unusable gaps before/after
     * 3. Lower daily load
     */
    public function chooseBestOperator(
        array $availableOperatorIds,
        Carbon $slotStart,
        int $duration,
        Carbon $date
    ): ?User {
        if (empty($availableOperatorIds)) {
            return null;
        }

        $operators = User::whereIn('id', $availableOperatorIds)->get()->keyBy('id');

        if ($operators->count() === 1) {
            return $operators->first();
        }

        $granularity = SystemSetting::getSlotGranularity();

        $scores = [];
        foreach ($availableOperatorIds as $operatorId) {
            $operator = $operators->get($operatorId);
            if ($operator) {
                $scores[$operatorId] = $this->score($operator, $slotStart, $duration, $date, $granularity);
            }
        }

        asort($scores);

        return $operators->get(array_key_first($scores));
    }

    private function score(User $operator, Carbon $slotStart, int $duration, Carbon $date, int $granularity): float
    {
        $slotEnd     = $slotStart->copy()->addMinutes($duration);
        $score       = 0.0;

        $workRanges  = $this->slots->getWorkRangesForOperator($operator, $date);
        $occupations = $this->slots->getOccupationsForOperator($operator, $date);
        $freeRanges  = $this->slots->calculateFreeRanges($workRanges, $occupations);

        $freeRange = null;
        foreach ($freeRanges as $range) {
            if ($range['start'] <= $slotStart && $range['end'] >= $slotEnd) {
                $freeRange = $range;
                break;
            }
        }

        if ($freeRange) {
            $gapBefore = $slotStart->diffInMinutes($freeRange['start']);
            $gapAfter  = $freeRange['end']->diffInMinutes($slotEnd);

            $score += $gapBefore + $gapAfter;

            if ($gapBefore > 0 && $gapBefore < $granularity) {
                $score += 100;
            }
            if ($gapAfter > 0 && $gapAfter < $granularity) {
                $score += 100;
            }
            if ($gapBefore === 0 && $gapAfter === 0) {
                $score -= 500;
            }
        }

        $score += $this->dailyLoadPercent($workRanges, $occupations) * 0.5;

        return $score;
    }

    private function dailyLoadPercent(array $workRanges, array $occupations): float
    {
        $totalWork = array_sum(array_map(
            fn ($r) => $r['start']->diffInMinutes($r['end']),
            $workRanges
        ));

        if ($totalWork === 0) {
            return 0.0;
        }

        $totalOccupied = array_sum(array_map(
            fn ($o) => $o['start']->diffInMinutes($o['end']),
            $occupations
        ));

        return ($totalOccupied / $totalWork) * 100;
    }
}
