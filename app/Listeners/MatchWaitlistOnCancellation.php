<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Jobs\NotifyWaitlistCandidateJob;
use App\Models\WaitlistEntry;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(AppointmentCancelled::class)]
class MatchWaitlistOnCancellation
{
    public function handle(AppointmentCancelled $event): void
    {
        $appointment = $event->appointment;

        $slotInfo = [
            'date'        => $appointment->scheduled_date->toDateString(),
            'time'        => $appointment->scheduled_date->format('H:i'),
            'staff_id'    => $appointment->staff_id,
            'service_ids' => $appointment->service_ids,
        ];

        $candidate = self::findCandidate($slotInfo, excludeIds: []);

        if ($candidate) {
            NotifyWaitlistCandidateJob::dispatch($candidate, $slotInfo, excludeIds: []);
        }
    }

    public static function findCandidate(array $slotInfo, array $excludeIds): ?WaitlistEntry
    {
        $date       = $slotInfo['date'];
        $time       = $slotInfo['time'];
        $dayName    = strtolower(\Carbon\Carbon::parse($date)->locale('en')->dayName);
        $serviceIds = $slotInfo['service_ids'];
        $staffId    = $slotInfo['staff_id'];

        return WaitlistEntry::waiting()
            ->when(! empty($excludeIds), fn ($q) => $q->whereNotIn('id', $excludeIds))
            ->oldest()
            ->get()
            ->first(function (WaitlistEntry $entry) use ($serviceIds, $date, $time, $dayName, $staffId) {
                $timeFrom = substr($entry->preferred_time_from, 0, 5);
                $timeTo   = substr($entry->preferred_time_to, 0, 5);

                return ! empty(array_intersect($entry->service_ids, $serviceIds))
                    && $date >= $entry->preferred_date_from->toDateString()
                    && $date <= $entry->preferred_date_to->toDateString()
                    && $time >= $timeFrom
                    && $time <= $timeTo
                    && in_array($dayName, $entry->preferred_days)
                    && ($entry->preferred_staff_id === null || $entry->preferred_staff_id === $staffId);
            });
    }
}
