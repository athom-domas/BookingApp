<?php

namespace App\Services\Booking;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentHold;
use App\Models\AppointmentReminder;
use App\Models\Service;
use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(
        private readonly SlotCalculationService $slotService,
        private readonly OperatorScoringService $scoringService,
    ) {}

    public function getAvailableSlots(array $params): array
    {
        return $this->slotService->getAvailableSlots($params);
    }

    public function getAvailableDates(array $params): array
    {
        $month      = $params['month'];
        $serviceIds = $params['serviceIds'];
        $staffId    = $params['staffId'] ?? null;
        $preference = $staffId ? 'specific' : 'any';

        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $end   = $start->copy()->endOfMonth();
        $today = Carbon::today();

        $available = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            if ($day->lt($today)) {
                continue;
            }

            $slots = $this->slotService->getAvailableSlots([
                'date'            => $day->toDateString(),
                'serviceIds'      => $serviceIds,
                'staffId'         => $staffId,
                'staffPreference' => $preference,
            ]);

            if (! empty($slots)) {
                $available[] = $day->toDateString();
            }
        }

        return $available;
    }

    /**
     * Creates a temporary hold after re-validating slot availability.
     *
     * @throws \RuntimeException when slot is unavailable or no services given
     */
    public function createHold(array $params): AppointmentHold
    {
        return DB::transaction(function () use ($params) {
            $serviceIds      = $params['serviceIds'] ?? [];
            $date            = Carbon::parse($params['date'])->startOfDay();
            $slotStart       = Carbon::parse($params['slotStart']);
            $slotEnd         = Carbon::parse($params['slotEnd']);
            $staffId         = $params['staffId'] ?? null;
            $staffPreference = $params['staffPreference'] ?? 'specific';
            $sessionId       = $params['sessionId'] ?? session()->getId();

            if (empty($serviceIds)) {
                throw new \RuntimeException('No services selected');
            }

            $duration = $this->slotService->calculateTotalDuration($serviceIds);

            if (! $this->isSlotAvailable($staffId, $date, $slotStart, $staffPreference, $serviceIds)) {
                throw new \RuntimeException('Slot no longer available - another booking took it');
            }

            if ($staffPreference === 'any') {
                $staffId = $this->pickBestOperator($date, $serviceIds, $slotStart, $duration);
                if (! $staffId) {
                    throw new \RuntimeException('Could not find available operator');
                }
            }

            return AppointmentHold::create([
                'staff_id'    => $staffId,
                'session_id'  => $sessionId,
                'customer_id' => Auth::id(),
                'starts_at'   => $slotStart,
                'ends_at'     => $slotEnd,
                'service_ids' => $serviceIds,
                'status'      => 'active',
                'expires_at'  => now()->addMinutes(SystemSetting::getHoldDuration()),
            ]);
        });
    }

    /**
     * Extends hold TTL.
     *
     * @throws \RuntimeException when hold is expired
     */
    public function extendHold(AppointmentHold $hold, ?int $minutes = null): AppointmentHold
    {
        if (! $hold->isActive()) {
            throw new \RuntimeException('Hold is not active');
        }

        $hold->extend($minutes ?? SystemSetting::getHoldExtension());

        return $hold;
    }

    /**
     * Converts an active hold into a confirmed appointment (atomic).
     *
     * @throws \RuntimeException when hold is expired or slot no longer available
     */
    public function confirmFromHold(AppointmentHold $hold, array $extra = []): Appointment
    {
        return DB::transaction(function () use ($hold, $extra) {
            // Re-fetch with pessimistic lock inside the transaction
            $hold = AppointmentHold::lockForUpdate()->findOrFail($hold->id);

            if (! $hold->isActive()) {
                throw new \RuntimeException('Hold expired or no longer active');
            }

            $date = $hold->starts_at->copy()->startOfDay();

            if (! $this->isSlotAvailable($hold->staff_id, $date, $hold->starts_at, 'specific', $hold->service_ids, $hold->id)) {
                $hold->markAsExpired();
                throw new \RuntimeException('Slot is no longer available - please select another time');
            }

            $serviceId = $hold->service_ids[0] ?? null;

            $appointment = Appointment::create([
                'user_id'        => $hold->customer_id ?? Auth::id(),
                'service_id'     => $serviceId,
                'service_ids'    => $hold->service_ids,
                'staff_id'       => $hold->staff_id,
                'scheduled_date' => $hold->starts_at,
                'status'         => 'confirmed',
                'final_price'    => $extra['final_price'] ?? null,
                'notes'          => $extra['notes'] ?? null,
            ]);

            $hold->markAsConverted();

            AppointmentConfirmed::dispatch($appointment);

            return $appointment;
        });
    }

    /**
     * Cancels an appointment if `canBeCancelled()` allows it.
     *
     * @throws \RuntimeException when cancellation not allowed
     */
    public function cancelAppointment(Appointment $appointment, ?string $reason = null): void
    {
        DB::transaction(function () use ($appointment, $reason) {
            if (! $appointment->canBeCancelled()) {
                throw new \RuntimeException('Appointment cannot be cancelled');
            }

            $appointment->update(['status' => 'cancelled']);

            AppointmentCancelled::dispatch($appointment, $reason);
        });
    }

    public static function cleanupExpiredHolds(): int
    {
        return AppointmentHold::expired()->update(['status' => 'expired']);
    }

    public function calculateTotalPrice(array $serviceIds): float
    {
        return (float) Service::whereIn('id', $serviceIds)->active()->sum('price');
    }

    public function bookDirect(array $params): Appointment
    {
        $userId             = $params['userId'];
        $serviceIds         = $params['serviceIds'];
        $staffId            = $params['staffId'] ?? null;
        $scheduledDate      = Carbon::parse($params['scheduledDate']);
        $confirmImmediately = $params['confirmImmediately'] ?? false;
        $notes              = $params['notes'] ?? null;
        $staffPreference    = $staffId ? 'specific' : 'any';

        return DB::transaction(function () use ($userId, $serviceIds, $staffId, $scheduledDate, $confirmImmediately, $notes, $staffPreference) {
            $date     = $scheduledDate->copy()->startOfDay();
            $slotTime = $scheduledDate->format('H:i');

            $slots = $this->slotService->getAvailableSlots([
                'date'            => $date,
                'serviceIds'      => $serviceIds,
                'staffId'         => $staffId,
                'staffPreference' => $staffPreference,
            ]);

            $matchingSlot = collect($slots)->first(fn ($s) => $s['start'] === $slotTime);

            if (! $matchingSlot) {
                throw new \RuntimeException('Slot non disponibile.');
            }

            if ($staffPreference === 'any') {
                $duration = $this->slotService->calculateTotalDuration($serviceIds);
                $staffId  = $this->pickBestOperator($date, $serviceIds, $scheduledDate, $duration);

                if (! $staffId) {
                    throw new \RuntimeException('Nessun operatore disponibile.');
                }
            }

            $appointment = Appointment::create([
                'user_id'        => $userId,
                'service_id'     => $serviceIds[0],
                'service_ids'    => $serviceIds,
                'staff_id'       => $staffId,
                'scheduled_date' => $scheduledDate,
                'status'         => $confirmImmediately ? 'confirmed' : 'pending',
                'final_price'    => $this->calculateTotalPrice($serviceIds),
                'notes'          => $notes,
            ]);

            AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'type'           => 'email',
                'scheduled_for'  => $scheduledDate->copy()->subDay(),
                'status'         => 'pending',
            ]);

            AppointmentReminder::create([
                'appointment_id' => $appointment->id,
                'type'           => 'email',
                'scheduled_for'  => $scheduledDate->copy()->subHours(2),
                'status'         => 'pending',
            ]);

            SyncGoogleCalendar::dispatch($appointment, 'create');

            if ($confirmImmediately) {
                AppointmentConfirmed::dispatch($appointment);
            }

            return $appointment;
        });
    }

    private function isSlotAvailable(
        ?int $staffId,
        Carbon $date,
        Carbon $slotStart,
        string $preference,
        array $serviceIds,
        ?int $excludeHoldId = null
    ): bool {
        $slots = $this->slotService->getAvailableSlots([
            'date'            => $date,
            'serviceIds'      => $serviceIds,
            'staffId'         => $staffId,
            'staffPreference' => $preference,
            'excludeHoldId'   => $excludeHoldId,
        ]);
        $slotTime  = $slotStart->format('H:i');

        foreach ($slots as $slot) {
            if ($slot['start'] === $slotTime) {
                // In 'any' mode the slot must still list this operator
                if ($preference === 'any' && $staffId !== null) {
                    return in_array($staffId, $slot['availableOperators'] ?? []);
                }

                return true;
            }
        }

        return false;
    }

    private function pickBestOperator(
        Carbon $date,
        array $serviceIds,
        Carbon $slotStart,
        int $duration
    ): ?int {
        $slots = $this->slotService->getAvailableSlots([
            'date'            => $date,
            'serviceIds'      => $serviceIds,
            'staffPreference' => 'any',
        ]);

        $slotTime = $slotStart->format('H:i');
        foreach ($slots as $slot) {
            if ($slot['start'] === $slotTime) {
                $operator = $this->scoringService->chooseBestOperator(
                    $slot['availableOperators'],
                    $slotStart,
                    $duration,
                    $date
                );

                return $operator?->id;
            }
        }

        return null;
    }
}
