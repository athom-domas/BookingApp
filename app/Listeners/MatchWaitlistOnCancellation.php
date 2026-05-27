<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Jobs\NotifyWaitlistCandidateJob;
use App\Models\WaitlistEntry;
use Illuminate\Database\Eloquent\Collection;

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

        foreach (self::findCandidates($slotInfo) as $candidate) {
            NotifyWaitlistCandidateJob::dispatch($candidate, $slotInfo);
        }
    }

    public static function findCandidates(array $slotInfo): Collection
    {
        $date       = $slotInfo['date'];
        $time       = $slotInfo['time'];
        $serviceIds = $slotInfo['service_ids'];
        $staffId    = $slotInfo['staff_id'];

        return WaitlistEntry::waiting()
            ->oldest()
            ->get()
            ->filter(function (WaitlistEntry $entry) use ($serviceIds, $date, $time, $staffId) {
                $timeFrom = substr($entry->preferred_time_from, 0, 5);
                $timeTo   = substr($entry->preferred_time_to, 0, 5);

                return ! empty(array_intersect(array_map('intval', $entry->service_ids), array_map('intval', $serviceIds)))
                    && $time >= $timeFrom
                    && $time <= $timeTo
                    && in_array($date, $entry->preferred_days)
                    && ($entry->preferred_staff_id === null || $entry->preferred_staff_id === $staffId);
            });
    }
}
