<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Jobs\SendCancellationNotification;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AppointmentService
{
    public function __construct(
        private readonly SlotCalculationService $slotService,
        private readonly PaymentService $paymentService,
    ) {}

    public function validateAvailability(int $staffId, array $serviceIds, Carbon $dateTime): bool
    {
        $services = Service::active()->whereIn('id', $serviceIds)->get();

        if ($services->isEmpty()) {
            return false;
        }

        foreach ($serviceIds as $serviceId) {
            if (! $this->staffCanProvideService(Service::find($serviceId), $staffId)) {
                return false;
            }
        }

        $rule = AvailabilityRule::where('user_id', $staffId)
            ->where('day_of_week', (int) $dateTime->dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (! $rule) {
            return false;
        }

        $totalDuration = $services->sum('duration_minutes');
        $ruleStart  = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->start_time);
        $ruleEnd    = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->end_time);
        $newApptEnd = $dateTime->copy()->addMinutes($totalDuration + config('booking.buffer_minutes', 0));

        if ($dateTime->lt($ruleStart) || $dateTime->gte($ruleEnd) || $newApptEnd->gt($ruleEnd)) {
            return false;
        }

        $conflicts = Appointment::where('staff_id', $staffId)
            ->where('status', '!=', 'cancelled')
            ->whereDate('scheduled_date', $dateTime->format('Y-m-d'))
            ->get();

        $allServiceIds = $conflicts->flatMap(fn ($a) => $a->service_ids ?? [])->unique()->values()->all();
        $durations     = Service::whereIn('id', $allServiceIds)->pluck('duration_minutes', 'id');

        foreach ($conflicts as $existing) {
            $sids          = $existing->service_ids ?? [];
            $existingDur   = collect($sids)->sum(fn ($id) => $durations[$id] ?? 0);
            $existingStart = $existing->scheduled_date;
            $existingEnd   = $existingStart->copy()->addMinutes($existingDur + config('booking.buffer_minutes', 0));

            if ($dateTime->lt($existingEnd) && $newApptEnd->gt($existingStart)) {
                return false;
            }
        }

        return true;
    }

    public function bookAppointment(int $userId, array $serviceIds, int $staffId, Carbon $scheduledDate): Appointment
    {
        $services = Service::active()->whereIn('id', $serviceIds)->get();

        if ($services->count() !== count($serviceIds)) {
            throw new BookingException('Uno o più servizi non disponibili.');
        }

        foreach ($services as $service) {
            if (! $this->staffCanProvideService($service, $staffId)) {
                throw new BookingException('Lo staff selezionato non eroga tutti i servizi richiesti.');
            }
        }

        if (! $this->validateAvailability($staffId, $serviceIds, $scheduledDate)) {
            throw new BookingException('Staff non disponibile per questa data e ora.');
        }

        return DB::transaction(function () use ($userId, $serviceIds, $services, $staffId, $scheduledDate): Appointment {
            if (! $this->validateAvailability($staffId, $serviceIds, $scheduledDate)) {
                throw new BookingException('Slot non più disponibile.');
            }

            $appointment = Appointment::create([
                'user_id'        => $userId,
                'service_ids'    => $serviceIds,
                'staff_id'       => $staffId,
                'scheduled_date' => $scheduledDate,
                'status'         => 'pending',
                'final_price'    => $services->sum('price'),
            ]);

            $reminderCount = SystemSetting::getReminderCount();
            if ($reminderCount >= 1) {
                AppointmentReminder::create([
                    'appointment_id' => $appointment->id,
                    'type'           => 'email',
                    'scheduled_for'  => $scheduledDate->copy()->subHours(SystemSetting::getReminder1Hours()),
                    'status'         => 'pending',
                ]);
            }
            if ($reminderCount >= 2) {
                AppointmentReminder::create([
                    'appointment_id' => $appointment->id,
                    'type'           => 'email',
                    'scheduled_for'  => $scheduledDate->copy()->subHours(SystemSetting::getReminder2Hours()),
                    'status'         => 'pending',
                ]);
            }

            SyncGoogleCalendar::dispatch($appointment, 'create');

            return $appointment;
        });
    }

    public function cancelAppointment(int $appointmentId, ?string $reason = null): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if (! $appointment->canBeCancelled()) {
            $hours = SystemSetting::getCancellationDeadlineHours();
            throw new BookingException("Impossibile cancellare: prenotazione non cancellabile o mancano meno di {$hours} ore.");
        }

        $appointment->update([
            'status' => 'cancelled',
            'notes'  => $reason ?? $appointment->notes,
        ]);

        $this->refundIfPaid($appointment);

        SendCancellationNotification::dispatch($appointment);
        SyncGoogleCalendar::dispatch($appointment, 'delete');
    }

    private function refundIfPaid(Appointment $appointment): void
    {
        $payment = $appointment->payment;

        if (! $payment || $payment->status !== 'completed' || $payment->payment_method !== 'stripe') {
            return;
        }

        try {
            $this->paymentService->refundPayment($payment->id);
        } catch (\Throwable $e) {
            Log::error('Auto-refund failed on cancellation', [
                'appointment_id' => $appointment->id,
                'payment_id'     => $payment->id,
                'error'          => $e->getMessage(),
            ]);
        }
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

    private function staffCanProvideService(?Service $service, int $staffId): bool
    {
        if (! $service) {
            return false;
        }

        $staff = User::find($staffId);

        return $staff !== null
            && $staff->roles()->where('name', 'staff')->where('guard_name', 'web')->exists()
            && $service->staff()->whereKey($staffId)->exists();
    }
}
