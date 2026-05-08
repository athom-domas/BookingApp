<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use Carbon\Carbon;

class AppointmentService
{
    public function validateAvailability(int $staffId, int $serviceId, Carbon $dateTime): bool
    {
        $rule = AvailabilityRule::where('user_id', $staffId)
            ->where('day_of_week', (int) $dateTime->dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (! $rule) {
            return false;
        }

        $ruleStart = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->start_time);
        $ruleEnd   = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->end_time);

        if ($dateTime->lt($ruleStart) || $dateTime->gte($ruleEnd)) {
            return false;
        }

        $service    = Service::findOrFail($serviceId);
        $newApptEnd = $dateTime->copy()->addMinutes($service->duration_minutes + config('booking.buffer_minutes'));

        $conflicts = Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $dateTime->format('Y-m-d'))
            ->with('service')
            ->get();

        foreach ($conflicts as $existing) {
            $existingStart = $existing->scheduled_date;
            $existingEnd   = $existingStart->copy()->addMinutes($existing->service->duration_minutes + config('booking.buffer_minutes'));

            if ($dateTime->lt($existingEnd) && $newApptEnd->gt($existingStart)) {
                return false;
            }
        }

        return true;
    }

    public function bookAppointment(int $userId, int $serviceId, int $staffId, Carbon $scheduledDate): Appointment
    {
        if (! $this->validateAvailability($staffId, $serviceId, $scheduledDate)) {
            throw new BookingException('Staff non disponibile per questa data e ora.');
        }

        $service     = Service::findOrFail($serviceId);
        $appointment = Appointment::create([
            'user_id'        => $userId,
            'service_id'     => $serviceId,
            'staff_id'       => $staffId,
            'scheduled_date' => $scheduledDate,
            'status'         => 'pending',
            'final_price'    => $service->price,
        ]);

        $slot = TimeSlot::where('user_id', $staffId)
            ->where('date', $scheduledDate->format('Y-m-d'))
            ->where('start_time', $scheduledDate->format('H:i:s'))
            ->where('is_available', true)
            ->first();

        if ($slot) {
            $slot->update(['is_available' => false, 'appointment_id' => $appointment->id]);
        } else {
            TimeSlot::create([
                'user_id'        => $staffId,
                'date'           => $scheduledDate->format('Y-m-d'),
                'start_time'     => $scheduledDate->format('H:i:s'),
                'end_time'       => $scheduledDate->copy()->addMinutes($service->duration_minutes)->format('H:i:s'),
                'is_available'   => false,
                'appointment_id' => $appointment->id,
            ]);
        }

        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'type'           => 'email',
            'scheduled_for'  => $scheduledDate->copy()->subDay(),
            'status'         => 'pending',
        ]);

        return $appointment;
    }

    public function cancelAppointment(int $appointmentId, ?string $reason = null): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if (! $appointment->canBeCancelled()) {
            throw new BookingException('Prenotazione non può essere cancellata.');
        }

        if (now()->diffInHours($appointment->scheduled_date, false) < 24) {
            throw new BookingException('Impossibile cancellare meno di 24 ore prima.');
        }

        $appointment->update([
            'status' => 'cancelled',
            'notes'  => $reason ?? $appointment->notes,
        ]);

        TimeSlot::where('appointment_id', $appointment->id)
            ->update(['is_available' => true, 'appointment_id' => null]);

        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'type'           => 'email',
            'scheduled_for'  => now(),
            'status'         => 'pending',
        ]);
    }

    public function getAvailableSlots(int $serviceId, int $staffId, string $date): array
    {
        $service = Service::findOrFail($serviceId);

        return TimeSlot::where('user_id', $staffId)
            ->where('date', $date)
            ->where('is_available', true)
            ->get()
            ->filter(function (TimeSlot $slot) use ($service): bool {
                $start = Carbon::parse($slot->start_time);
                $end   = Carbon::parse($slot->end_time);
                return $start->diffInMinutes($end) >= $service->duration_minutes;
            })
            ->values()
            ->map(fn (TimeSlot $slot): array => [
                'start_time' => $slot->start_time,
                'end_time'   => $slot->end_time,
            ])
            ->all();
    }
}
