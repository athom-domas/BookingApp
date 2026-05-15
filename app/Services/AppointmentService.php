<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Jobs\SendCancellationNotification;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private readonly SlotCalculationService $slotService) {}

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

        $ruleStart  = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->start_time);
        $ruleEnd    = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->end_time);
        $newApptEnd = $dateTime->copy()->addMinutes($service->duration_minutes + config('booking.buffer_minutes', 0));

        if ($dateTime->lt($ruleStart) || $dateTime->gte($ruleEnd) || $newApptEnd->gt($ruleEnd)) {
            return false;
        }

        $conflicts = Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $dateTime->format('Y-m-d'))
            ->with('service')
            ->get();

        foreach ($conflicts as $existing) {
            $existingStart = $existing->scheduled_date;
            $existingEnd   = $existingStart->copy()->addMinutes(
                $existing->service->duration_minutes + config('booking.buffer_minutes', 0)
            );

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
            // Re-check inside transaction to prevent double booking
            if (! $this->validateAvailability($staffId, $serviceId, $scheduledDate)) {
                throw new BookingException('Slot non più disponibile.');
            }

            $appointment = Appointment::create([
                'user_id'        => $userId,
                'service_id'     => $serviceId,
                'staff_id'       => $staffId,
                'scheduled_date' => $scheduledDate,
                'status'         => 'pending',
                'final_price'    => $service->price,
            ]);

            AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'type'           => 'email',
                'scheduled_for'  => $scheduledDate->copy()->subDay(),
                'status'         => 'pending',
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
            'notes'  => $reason ?? $appointment->notes,
        ]);

        SendCancellationNotification::dispatch($appointment);
        SyncGoogleCalendar::dispatch($appointment, 'delete');
    }

    public function getAvailableSlots(int $serviceId, int $staffId, string $date): array
    {
        $service = Service::active()->find($serviceId);

        if (! $service || ! $this->staffCanProvideService($service, $staffId)) {
            return [];
        }

        $slots = $this->slotService->getAvailableSlots([
            'date'            => $date,
            'serviceIds'      => [$serviceId],
            'staffId'         => $staffId,
            'staffPreference' => 'specific',
        ]);

        return array_map(fn($slot) => [
            'start_time' => $slot['start'],
            'end_time'   => $slot['end'],
        ], $slots);
    }

    private function staffCanProvideService(Service $service, int $staffId): bool
    {
        $staff = User::find($staffId);

        return $staff !== null
            && $staff->roles()->where('name', 'staff')->where('guard_name', 'web')->exists()
            && $service->staff()->whereKey($staffId)->exists();
    }
}
