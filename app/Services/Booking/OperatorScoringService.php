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

        $scores = [];
        foreach ($availableOperatorIds as $operatorId) {
            $operator = $operators->get($operatorId);
            if ($operator) {
                $scores[$operatorId] = $this->score($operator, $slotStart, $duration, $date);
            }
        }

        asort($scores);

        return $operators->get(array_key_first($scores));
    }

    private function score(User $operator, Carbon $slotStart, int $duration, Carbon $date): float
    {
        $slotEnd     = $slotStart->copy()->addMinutes($duration);
        $minGap      = SystemSetting::getSlotGranularity();
        $score       = 0.0;

        $freeRange = $this->findContainingFreeRange($operator, $slotStart, $slotEnd, $date);

        if ($freeRange) {
            $gapBefore = $slotStart->diffInMinutes($freeRange['start']);
            $gapAfter  = $freeRange['end']->diffInMinutes($slotEnd);

            $score += $gapBefore + $gapAfter;

            if ($gapBefore > 0 && $gapBefore < $minGap) {
                $score += 100;
            }
            if ($gapAfter > 0 && $gapAfter < $minGap) {
                $score += 100;
            }
            if ($gapBefore === 0 && $gapAfter === 0) {
                $score -= 500;
            }
        }

        $score += $this->dailyLoadPercent($operator, $date) * 0.5;

        return $score;
    }

    private function findContainingFreeRange(
        User $operator,
        Carbon $slotStart,
        Carbon $slotEnd,
        Carbon $date
    ): ?array {
        $freeRanges = $this->slots->getFreeRangesForOperator($operator, $date);

        foreach ($freeRanges as $range) {
            if ($range['start'] <= $slotStart && $range['end'] >= $slotEnd) {
                return $range;
            }
        }

        return null;
    }

    private function dailyLoadPercent(User $operator, Carbon $date): float
    {
        $workRanges = $this->slots->getWorkRangesForOperator($operator, $date);
        $totalWork  = array_sum(array_map(
            fn ($r) => $r['start']->diffInMinutes($r['end']),
            $workRanges
        ));

        if ($totalWork === 0) {
            return 0.0;
        }

        $occupations   = $this->slots->getOccupationsForOperator($operator, $date);
        $totalOccupied = array_sum(array_map(
            fn ($o) => $o['start']->diffInMinutes($o['end']),
            $occupations
        ));

        return ($totalOccupied / $totalWork) * 100;
    }
}
