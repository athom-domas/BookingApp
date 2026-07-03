<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AvailabilityRule;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuggestedSlotsController extends Controller
{
    public function __construct(private readonly SlotCalculationService $slotService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'serviceIds'      => ['required', 'array', 'min:1'],
            'serviceIds.*'    => ['integer', 'exists:services,id'],
            'staffId'         => ['nullable', 'integer', 'exists:users,id'],
            'preferredDays'   => ['nullable', 'array'],
            'preferredDays.*' => ['integer', 'between:0,6'],
            'timeFrom'        => ['nullable', 'date_format:H:i'],
            'timeTo'          => ['nullable', 'date_format:H:i', 'after:timeFrom'],
            'limit'           => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $serviceIds    = array_map('intval', $request->input('serviceIds'));
        $staffId       = $request->filled('staffId') ? (int) $request->input('staffId') : null;
        $preferredDays = $request->input('preferredDays') ? array_map('intval', $request->input('preferredDays')) : null;
        $timeFrom      = $request->input('timeFrom');
        $timeTo        = $request->input('timeTo');
        $limit         = (int) ($request->input('limit', 6));

        $openDays   = AvailabilityRule::where('is_available', true)->distinct()->pluck('day_of_week')->all();
        $targetDays = (empty($preferredDays)) ? $openDays : $preferredDays;

        $results = [];
        $today   = Carbon::today();

        for ($i = 0; $i < 28 && count($results) < $limit * 3; $i++) {
            $date = $today->copy()->addDays($i);
            $dow  = (int) $date->format('w');

            if (! in_array($dow, $targetDays, true)) {
                continue;
            }

            $slots = $this->slotService->getAvailableSlots([
                'date'            => $date,
                'serviceIds'      => $serviceIds,
                'staffId'         => $staffId,
                'staffPreference' => $staffId ? 'specific' : 'any',
            ]);

            foreach ($slots as $slot) {
                $score     = $this->score($dow, $slot['start'], $preferredDays, $timeFrom, $timeTo);
                $results[] = [
                    'date'  => $date->toDateString(),
                    'time'  => $slot['start'],
                    'score' => $score,
                ];
            }
        }

        usort($results, fn ($a, $b) =>
            $b['score'] <=> $a['score']
                ?: strcmp($a['date'], $b['date'])
                ?: strcmp($a['time'], $b['time'])
        );

        return response()->json(['data' => array_slice($results, 0, $limit)]);
    }

    private function score(int $dow, string $time, ?array $preferredDays, ?string $timeFrom, ?string $timeTo): int
    {
        $score = 0;
        if ($preferredDays && in_array($dow, $preferredDays, true)) {
            $score += 2;
        }
        if ($timeFrom && $timeTo) {
            if ($time >= $timeFrom && $time < $timeTo) {
                $score += 2;
            } else {
                $slotMin = (int) substr($time, 0, 2) * 60 + (int) substr($time, 3, 2);
                $fromMin = (int) substr($timeFrom, 0, 2) * 60 + (int) substr($timeFrom, 3, 2);
                $toMin   = (int) substr($timeTo, 0, 2) * 60 + (int) substr($timeTo, 3, 2);
                if ($slotMin >= $fromMin - 60 && $slotMin < $toMin + 60) {
                    $score += 1;
                }
            }
        }
        return $score;
    }
}
