<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\TimeSlot;
use Carbon\Carbon;

class SlotGeneratorService
{
    public function generateWeeklySlots(int $staffId, Carbon $weekStart, int $slotMinutes = 60): int
    {
        $created = 0;

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date      = $weekStart->copy()->addDays($dayOffset);
            $dayOfWeek = (int) $date->dayOfWeek;

            $rule = AvailabilityRule::where('user_id', $staffId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->first();

            if (! $rule) {
                continue;
            }

            $blockedWindows = $this->getBlockedWindows($staffId, $date);
            $created += $this->generateDaySlots($staffId, $date, $rule, $slotMinutes, $blockedWindows);
        }

        return $created;
    }

    private function getBlockedWindows(int $staffId, Carbon $date): array
    {
        return Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $date->format('Y-m-d'))
            ->with('service')
            ->get()
            ->map(function (Appointment $appt): array {
                $start = Carbon::parse($appt->scheduled_date);
                $end   = $start->copy()->addMinutes($appt->service->duration_minutes + config('booking.buffer_minutes'));
                return ['start' => $start, 'end' => $end];
            })
            ->all();
    }

    private function generateDaySlots(int $staffId, Carbon $date, AvailabilityRule $rule, int $slotMinutes, array $blockedWindows): int
    {
        $created   = 0;
        $slotStart = Carbon::parse($date->format('Y-m-d') . ' ' . $rule->start_time);
        $windowEnd = Carbon::parse($date->format('Y-m-d') . ' ' . $rule->end_time);

        while ($slotStart->copy()->addMinutes($slotMinutes)->lte($windowEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);

            if (! $this->overlapsAny($slotStart, $slotEnd, $blockedWindows)) {
                $slot = TimeSlot::firstOrCreate(
                    [
                        'user_id'    => $staffId,
                        'date'       => $date->format('Y-m-d'),
                        'start_time' => $slotStart->format('H:i:s'),
                        'end_time'   => $slotEnd->format('H:i:s'),
                    ],
                    ['is_available' => true]
                );

                if ($slot->wasRecentlyCreated) {
                    $created++;
                }
            }

            $slotStart->addMinutes($slotMinutes);
        }

        return $created;
    }

    private function overlapsAny(Carbon $slotStart, Carbon $slotEnd, array $blockedWindows): bool
    {
        foreach ($blockedWindows as $window) {
            if ($slotStart->lt($window['end']) && $slotEnd->gt($window['start'])) {
                return true;
            }
        }

        return false;
    }
}
