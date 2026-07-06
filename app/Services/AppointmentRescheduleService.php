<?php

namespace App\Services;

use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use App\Mail\AdminRescheduleNotificationMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AppointmentRescheduleService
{
    public function __construct(private SlotCalculationService $slots) {}

    public function reschedule(
        Appointment $appointment,
        Carbon $newDateTime,
        User $actor,
    ): Appointment {
        $updated = DB::transaction(function () use ($appointment, $newDateTime, $actor): Appointment {
            $appointment = Appointment::withoutGlobalScope('business')
                ->where('id', $appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Permessi
            if ($appointment->business_id !== $actor->business_id) {
                throw new RescheduleConflictException(
                    'Appuntamento non trovato.',
                    RescheduleConflictException::FORBIDDEN,
                );
            }

            $canManageAny = $actor->isAdmin() || $actor->can('appointments.view_all');

            if (! $canManageAny && $appointment->staff_id !== $actor->id) {
                throw new RescheduleConflictException(
                    'Non sei autorizzato a spostare questo appuntamento.',
                    RescheduleConflictException::FORBIDDEN,
                );
            }

            // 2. Status
            if (! in_array($appointment->status, ['pending', 'confirmed'])) {
                throw new RescheduleConflictException(
                    'Solo gli appuntamenti in attesa o confermati possono essere spostati.',
                    RescheduleConflictException::WRONG_STATUS,
                );
            }

            // 3. Fasce lavorative + blockout
            $serviceIds = $appointment->service_ids
                ?? ($appointment->service_id ? [$appointment->service_id] : []);

            $duration = $this->slots->calculateTotalDuration($serviceIds);
            $slotEnd  = $newDateTime->copy()->addMinutes($duration);
            $date     = $newDateTime->copy()->startOfDay();

            $workRanges = $this->slots->getWorkRangesForOperator($appointment->staff, $date);

            $fitsInWorkRange = collect($workRanges)->contains(
                fn ($range) => $range['start'] <= $newDateTime && $range['end'] >= $slotEnd,
            );

            if (! $fitsInWorkRange) {
                throw new RescheduleConflictException(
                    'Lo slot alle ' . $newDateTime->format('H:i') . ' del ' . $newDateTime->format('d/m/Y') . ' è fuori orario o in un periodo bloccato.',
                    RescheduleConflictException::OUTSIDE_HOURS,
                );
            }

            // 4. Conflitti con altri appuntamenti — esclude self, lockForUpdate su query
            $others = Appointment::where('business_id', $appointment->business_id)
                ->where('staff_id', $appointment->staff_id)
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('scheduled_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->lockForUpdate()
                ->get();

            foreach ($others as $other) {
                $otherDur   = $this->durationFor($other);
                $otherStart = $other->scheduled_date;
                $otherEnd   = $other->scheduled_date->copy()->addMinutes($otherDur);

                if ($newDateTime < $otherEnd && $slotEnd > $otherStart) {
                    throw new RescheduleConflictException(
                        'Conflitto con un altro appuntamento alle ' . $otherStart->format('H:i') . '.',
                        RescheduleConflictException::CONFLICT,
                    );
                }
            }

            // 5. Salva
            $appointment->update(['scheduled_date' => $newDateTime]);

            return $appointment->fresh();
        });

        $this->notifyReschedule($updated, $actor);

        return $updated;
    }

    private function notifyReschedule(Appointment $appointment, User $actor): void
    {
        if (! $appointment->user_id || ! $appointment->staff_id) {
            return;
        }

        $appointment->loadMissing('user.preferences', 'staff');

        $byAdmin = $actor->isAdmin() || $actor->isStaff();

        if ($byAdmin) {
            $channel = $appointment->user?->preferences?->notification_channel ?? 'email';

            if ($channel === 'whatsapp') {
                app(WhatsAppNotificationService::class)->dispatchForAppointment(
                    $appointment,
                    'appointment_rescheduled',
                    WhatsAppNotificationService::appointmentParams($appointment),
                );
            } else {
                Mail::send(new \App\Mail\AppointmentRescheduleMail($appointment));
            }
        } else {
            $admins = User::role('admin')
                ->whereHas('businesses', fn ($q) => $q->where('businesses.id', $appointment->business_id))
                ->get();

            foreach ($admins as $admin) {
                if ($admin->receive_email_notifications) {
                    Mail::to($admin->email)->send(new AdminRescheduleNotificationMail($appointment));
                }
            }
        }
    }

    private function durationFor(Appointment $appointment): int
    {
        $serviceIds = $appointment->service_ids ?? [];

        return $this->slots->calculateTotalDuration($serviceIds) ?: 30;
    }
}
