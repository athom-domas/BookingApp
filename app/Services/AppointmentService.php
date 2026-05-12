<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Jobs\SendCancellationNotification;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function validateAvailability(int $staffId, int $serviceId, Carbon $dateTime): bool
    {
        $service = Service::active()->find($serviceId);

        if (! $service || ! $this->staffCanProvideService($service, $staffId)) {
            return false;
        }

        $rule = AvailabilityRule::where('user_id', $staffId)
            ->where('day_of_week', (int) $dateTime->dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (! $rule) {
            return false;
        }

        $ruleStart = Carbon::parse($dateTime->format('Y-m-d').' '.$rule->start_time);
        $ruleEnd = Carbon::parse($dateTime->format('Y-m-d').' '.$rule->end_time);

        if ($dateTime->lt($ruleStart) || $dateTime->gte($ruleEnd)) {
            return false;
        }

        $newApptEnd = $dateTime->copy()->addMinutes($service->duration_minutes + config('booking.buffer_minutes'));

        if ($newApptEnd->gt($ruleEnd)) {
            return false;
        }

        $conflicts = Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $dateTime->format('Y-m-d'))
            ->with('service')
            ->get();

        foreach ($conflicts as $existing) {
            $existingStart = $existing->scheduled_date;
            $existingEnd = $existingStart->copy()->addMinutes($existing->service->duration_minutes + config('booking.buffer_minutes'));

            if ($dateTime->lt($existingEnd) && $newApptEnd->gt($existingStart)) {
                return false;
            }
        }

        return true;
    }

    public function bookAppointment(int $userId, int $serviceId, int $staffId, Carbon $scheduledDate): Appointment
    {
        $service = Service::findOrFail($serviceId);

        if (! $service->active) {
            throw new BookingException('Servizio non disponibile.');
        }

        if (! $this->staffCanProvideService($service, $staffId)) {
            throw new BookingException('Lo staff selezionato non eroga questo servizio.');
        }

        if (! $this->validateAvailability($staffId, $serviceId, $scheduledDate)) {
            throw new BookingException('Staff non disponibile per questa data e ora.');
        }

        return DB::transaction(function () use ($userId, $serviceId, $staffId, $scheduledDate, $service): Appointment {
            $slot = TimeSlot::where('user_id', $staffId)
                ->where('date', $scheduledDate->format('Y-m-d'))
                ->where('start_time', $scheduledDate->format('H:i:s'))
                ->where('is_available', true)
                ->whereNull('appointment_id')
                ->lockForUpdate()
                ->first();

            if (! $slot || ! $this->slotFitsService($slot, $service)) {
                throw new BookingException('Slot non più disponibile.');
            }

            if (! $this->validateAvailability($staffId, $serviceId, $scheduledDate)) {
                throw new BookingException('Staff non disponibile per questa data e ora.');
            }

            $appointment = Appointment::create([
                'user_id' => $userId,
                'service_id' => $serviceId,
                'staff_id' => $staffId,
                'scheduled_date' => $scheduledDate,
                'status' => 'pending',
                'final_price' => $service->price,
            ]);

            $slot->update(['is_available' => false, 'appointment_id' => $appointment->id]);

            AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'type' => 'email',
                'scheduled_for' => $scheduledDate->copy()->subDay(),
                'status' => 'pending',
            ]);

            SyncGoogleCalendar::dispatch($appointment, 'create');

            return $appointment;
        });
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
            'notes' => $reason ?? $appointment->notes,
        ]);

        TimeSlot::where('appointment_id', $appointment->id)
            ->update(['is_available' => true, 'appointment_id' => null]);

        SendCancellationNotification::dispatch($appointment);
        SyncGoogleCalendar::dispatch($appointment, 'delete');
    }

    public function getAvailableSlots(int $serviceId, int $staffId, string $date): array
    {
        $service = Service::active()->find($serviceId);

        if (! $service || ! $this->staffCanProvideService($service, $staffId)) {
            return [];
        }

        return TimeSlot::where('user_id', $staffId)
            ->where('date', $date)
            ->where('is_available', true)
            ->whereNull('appointment_id')
            ->get()
            ->filter(fn (TimeSlot $slot): bool => $this->slotFitsService($slot, $service))
            ->values()
            ->map(fn (TimeSlot $slot): array => [
                'start_time' => $slot->start_time,
                'end_time' => $slot->end_time,
            ])
            ->all();
    }

    private function staffCanProvideService(Service $service, int $staffId): bool
    {
        $staff = User::find($staffId);

        return $staff !== null
            && $staff->roles()->where('name', 'staff')->where('guard_name', 'web')->exists()
            && $service->staff()->whereKey($staffId)->exists();
    }

    private function slotFitsService(TimeSlot $slot, Service $service): bool
    {
        $start = Carbon::parse($slot->date->format('Y-m-d').' '.$slot->start_time);
        $end = Carbon::parse($slot->date->format('Y-m-d').' '.$slot->end_time);

        return $start->diffInMinutes($end) >= $service->duration_minutes;
    }
}
