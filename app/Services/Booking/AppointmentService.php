<?php

namespace App\Services\Booking;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Service;
use Carbon\Carbon;
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
