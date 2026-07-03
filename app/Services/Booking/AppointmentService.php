<?php

namespace App\Services\Booking;

use App\Events\AppointmentCancelled;
use App\Events\AppointmentConfirmed;
use App\Jobs\SyncGoogleCalendar;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Service;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
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
        $businessId = app()->bound('current_business_id') ? app('current_business_id') : null;

        if (! $businessId) {
            return $this->slotService->getAvailableDatesForMonth($params);
        }

        $month   = $params['month'] ?? now()->format('Y-m');
        $version = (int) Cache::get("booking_dates_v:{$businessId}:{$month}", 0);
        $svcKey  = implode(',', collect($params['serviceIds'] ?? [])->sort()->values()->all());
        $staffId = $params['staffId'] ?? 0;
        $pref    = $params['staffPreference'] ?? 'any';

        $key = "booking_dates:{$businessId}:{$month}:v{$version}:{$svcKey}:{$staffId}:{$pref}";

        return Cache::remember($key, 300, fn () =>
            $this->slotService->getAvailableDatesForMonth($params)
        );
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

        $appointment = DB::transaction(function () use ($userId, $serviceIds, $staffId, $scheduledDate, $confirmImmediately, $notes, $staffPreference) {
            $date = $scheduledDate->copy()->startOfDay();

            // Grace period: allow slots up to one granularity window in the past to avoid
            // race conditions between slot display and form submission (especially for today).
            $graceCutoff = Carbon::now()->subMinutes(SystemSetting::getSlotGranularity());
            if ($scheduledDate->lt($graceCutoff)) {
                throw new \RuntimeException('Slot non disponibile.');
            }

            // Validate against work ranges and conflicts without the "today from now" UI cutoff.
            $slotFree = $this->slotService->isSlotFree([
                'date'            => $date,
                'slotStart'       => $scheduledDate,
                'serviceIds'      => $serviceIds,
                'staffId'         => $staffId,
                'staffPreference' => $staffPreference,
            ]);

            if (! $slotFree) {
                throw new \RuntimeException('Slot non disponibile.');
            }

            if ($staffPreference === 'any') {
                $duration = $this->slotService->calculateTotalDuration($serviceIds);
                $staffId  = $this->pickBestOperator($date, $serviceIds, $scheduledDate, $duration);

                if (! $staffId) {
                    throw new \RuntimeException('Nessun operatore disponibile.');
                }
            }

            $businessId = User::find($staffId)?->business_id
                ?? (app()->bound('current_business_id') ? app('current_business_id') : null);

            $appointment = Appointment::create([
                'user_id'        => $userId,
                'service_ids'    => $serviceIds,
                'staff_id'       => $staffId,
                'scheduled_date' => $scheduledDate,
                'status'         => $confirmImmediately ? 'confirmed' : 'pending',
                'final_price'    => $this->calculateTotalPrice($serviceIds),
                'notes'          => $notes,
                'business_id'    => $businessId,
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

        if ($confirmImmediately) {
            AppointmentConfirmed::dispatch($appointment);
        }

        return $appointment;
    }

    private function pickBestOperator(
        Carbon $date,
        array $serviceIds,
        Carbon $slotStart,
        int $duration
    ): ?int {
        $availableIds = $this->slotService->getAvailableOperatorsForSlot([
            'date'       => $date,
            'slotStart'  => $slotStart,
            'serviceIds' => $serviceIds,
        ]);

        if (empty($availableIds)) {
            return null;
        }

        $operator = $this->scoringService->chooseBestOperator(
            $availableIds,
            $slotStart,
            $duration,
            $date
        );

        return $operator?->id;
    }
}
