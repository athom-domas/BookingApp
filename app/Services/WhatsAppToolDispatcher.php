<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class WhatsAppToolDispatcher
{
    private array $whitelist = [
        'list_services', 'list_staff_for_service', 'list_available_slots',
        'select_slot', 'book_appointment', 'get_next_appointment', 'cancel_appointment',
        'request_human_handoff',
    ];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly SlotCalculationService $slotService,
        private readonly WalkInService $walkInService,
    ) {}

    public function dispatch(array $toolCall, array &$state, int $businessId): array
    {
        $name = $toolCall['name'] ?? '';
        $input = $toolCall['input'] ?? [];

        if (! in_array($name, $this->whitelist, true)) {
            return ['ok' => false, 'code' => 'SERVICE_NOT_FOUND', 'message' => "Tool '{$name}' not allowed."];
        }

        return match ($name) {
            'list_services' => $this->listServices($businessId),
            'list_staff_for_service' => $this->listStaffForService($input, $businessId),
            'list_available_slots' => $this->listAvailableSlots($input, $state, $businessId),
            'select_slot' => $this->selectSlot($input, $state, $businessId),
            'book_appointment' => $this->bookAppointment($input, $state, $businessId),
            'get_next_appointment' => $this->getNextAppointment($state, $businessId),
            'cancel_appointment' => $this->cancelAppointment($input, $state, $businessId),
            'request_human_handoff' => $this->requestHumanHandoff($input, $state, $businessId),
        };
    }

    private function listServices(int $businessId): array
    {
        $services = Service::where('business_id', $businessId)->active()->get(['id', 'name', 'duration_minutes', 'price']);

        return ['ok' => true, 'services' => $services->toArray()];
    }

    private function listStaffForService(array $input, int $businessId): array
    {
        $serviceId = (int) ($input['service_id'] ?? 0);
        $service = Service::where('business_id', $businessId)->where('id', $serviceId)->first();

        if (! $service) {
            return ['ok' => false, 'code' => 'SERVICE_NOT_FOUND', 'message' => 'Servizio non trovato.'];
        }

        $staff = $service->staff()->where('users.business_id', $businessId)->get(['users.id', 'users.name']);

        return ['ok' => true, 'staff' => $staff->toArray()];
    }

    private function listAvailableSlots(array $input, array &$state, int $businessId): array
    {
        if (! $this->isBookingEnabled($businessId)) {
            return ['ok' => false, 'code' => 'BOOKING_DISABLED', 'message' => 'La prenotazione via WhatsApp non è attiva per questo salone.'];
        }

        $serviceIds = array_map('intval', (array) ($input['service_ids'] ?? []));
        $staffId = isset($input['staff_id']) ? (int) $input['staff_id'] : null;
        $date = $input['date'] ?? null;

        if (empty($serviceIds) || ! $date) {
            return ['ok' => false, 'code' => 'MISSING_PARAMS', 'message' => 'service_ids e date sono obbligatori.'];
        }

        $slots = $this->slotService->getAvailableSlots([
            'date' => $date,
            'serviceIds' => $serviceIds,
            'staffId' => $staffId,
            'staffPreference' => $staffId ? 'specific' : 'any',
        ]);

        $allOperatorIds = collect($slots)->flatMap(fn ($s) => $s['availableOperators'] ?? [])->unique()->values()->all();
        $staffNames = User::whereIn('id', $allOperatorIds)->pluck('name', 'id');

        // Load existing appointments for the day to compute adjacency score
        $serviceDuration = $this->slotService->calculateTotalDuration($serviceIds) ?: 30;
        $dayAppointments = Appointment::where('business_id', $businessId)
            ->whereIn('staff_id', $allOperatorIds)
            ->whereBetween('scheduled_date', [
                Carbon::parse($date)->startOfDay(),
                Carbon::parse($date)->endOfDay(),
            ])
            ->whereNotIn('status', ['cancelled'])
            ->get(['id', 'staff_id', 'scheduled_date', 'service_ids']);

        // Pre-load service durations for all appointments in one query
        $allApptServiceIds = $dayAppointments->flatMap(fn ($a) => $a->service_ids ?? [])->unique()->values()->all();
        $apptServiceDurations = Service::whereIn('id', $allApptServiceIds)->pluck('duration_minutes', 'id');

        $enriched = array_map(function (array $slot) use ($date, $staffNames, $dayAppointments, $serviceDuration, $apptServiceDurations) {
            $slot['starts_at'] = Carbon::createFromFormat('Y-m-d H:i', $date.' '.$slot['start'])->toIso8601String();
            $slot['availableStaff'] = array_map(
                fn ($id) => ['id' => $id, 'name' => $staffNames[$id] ?? "Staff #{$id}"],
                $slot['availableOperators'] ?? []
            );

            $slotStart = Carbon::parse($slot['starts_at']);
            $slotEnd = $slotStart->copy()->addMinutes($serviceDuration);
            $score = 0;

            foreach ($slot['availableOperators'] ?? [] as $opId) {
                foreach ($dayAppointments->where('staff_id', $opId) as $appt) {
                    $apptDuration = collect($appt->service_ids ?? [])->sum(fn ($id) => $apptServiceDurations[$id] ?? 0) ?: 30;
                    $apptStart = Carbon::parse($appt->scheduled_date);
                    $apptEnd = $apptStart->copy()->addMinutes($apptDuration);

                    $gapBefore = $apptEnd->diffInMinutes($slotStart, false);  // >0: appt ends before slot starts
                    $gapAfter = $slotEnd->diffInMinutes($apptStart, false);  // >0: slot ends before appt starts

                    if ($gapBefore >= 0 && $gapBefore <= 5) {
                        $score = max($score, 40);
                    } elseif ($gapBefore > 0 && $gapBefore <= 15) {
                        $score = max($score, 25);
                    }
                    if ($gapAfter >= 0 && $gapAfter <= 5) {
                        $score = max($score, 35);
                    } elseif ($gapAfter > 0 && $gapAfter <= 15) {
                        $score = max($score, 20);
                    }
                }
            }

            $slot['score'] = $score;
            $slot['label'] = match (true) {
                $score >= 35 => 'consigliato',
                $score >= 20 => 'buono',
                default => 'disponibile',
            };

            return $slot;
        }, $slots);

        usort($enriched, fn ($a, $b) => $b['score'] - $a['score']);

        $state['last_available_slots'] = $enriched;
        $state['last_available_slots_generated_at'] = now()->toIso8601String();
        $state['last_available_slots_service_ids'] = $serviceIds;
        // Invalidate any pending slot selection: the customer is searching again
        $state['selected_slot'] = null;
        $state['awaiting_confirmation'] = false;

        // Compact representation: strip raw fields not needed by Claude to reduce token count.
        // Full list stays in state for slot validation.
        $compactSlots = array_map(fn ($s) => [
            'start' => $s['start'],
            'starts_at' => $s['starts_at'],
            'label' => $s['label'],
            'staff' => array_map(fn ($st) => $st['id'].':'.$st['name'], $s['availableStaff']),
        ], $enriched);

        return ['ok' => true, 'slots' => $compactSlots, 'service_ids_searched' => $serviceIds];
    }

    private function selectSlot(array $input, array &$state, int $businessId): array
    {
        if (! $this->isBookingEnabled($businessId)) {
            return ['ok' => false, 'code' => 'BOOKING_DISABLED', 'message' => 'La prenotazione via WhatsApp non è attiva per questo salone.'];
        }

        $startsAt = $input['starts_at'] ?? null;
        $staffId = isset($input['staff_id']) ? (int) $input['staff_id'] : null;

        // Service IDs: prefer state (saved by list_available_slots), fallback to input
        $serviceIds = $state['last_available_slots_service_ids'] ?? [];
        if (empty($serviceIds)) {
            $serviceIds = array_map('intval', (array) ($input['service_ids'] ?? []));
            if (empty($serviceIds) && isset($input['service_id'])) {
                $serviceIds = [(int) $input['service_id']];
            }
        }

        if (! $startsAt || empty($serviceIds)) {
            return ['ok' => false, 'code' => 'MISSING_PARAMS', 'message' => 'starts_at e service_ids sono obbligatori.'];
        }

        $proposedSlots = $state['last_available_slots'] ?? [];
        $proposedStarts = array_column($proposedSlots, 'starts_at');
        if (! in_array($startsAt, $proposedStarts, true)) {
            return ['ok' => false, 'code' => 'SLOT_NOT_IN_LIST', 'message' => 'Lo slot indicato non è tra quelli restituiti da list_available_slots. Chiama list_available_slots di nuovo.'];
        }

        $matchedSlot = collect($proposedSlots)->firstWhere('starts_at', $startsAt);
        $availableOpIds = array_map('intval', $matchedSlot['availableOperators'] ?? []);
        $freshAvailableOpIds = $this->slotService->getAvailableOperatorsForSlot([
            'date' => Carbon::parse($startsAt)->toDateString(),
            'slotStart' => $startsAt,
            'serviceIds' => $serviceIds,
        ]);

        if (! empty($availableOpIds)) {
            $freshAvailableOpIds = array_values(array_intersect($availableOpIds, $freshAvailableOpIds));
        }

        if (! $staffId) {
            $staffId = $freshAvailableOpIds[0] ?? 0;
            if (! $staffId) {
                return ['ok' => false, 'code' => 'NO_STAFF', 'message' => 'Nessun operatore disponibile per questo slot.'];
            }
        } elseif (! in_array($staffId, $freshAvailableOpIds, true)) {
            $available = implode(', ', $freshAvailableOpIds);

            return ['ok' => false, 'code' => 'STAFF_NOT_AVAILABLE', 'message' => "Lo staff #{$staffId} non è disponibile per questo slot. Operatori disponibili: [{$available}]. Chiama list_available_slots con staff_id={$staffId} per trovare slot compatibili."];
        }

        $serviceNames = Service::where('business_id', $businessId)->whereIn('id', $serviceIds)->pluck('name')->join(', ');
        $staff = User::where('business_id', $businessId)->where('id', $staffId)->value('name');

        $state['selected_slot'] = [
            'starts_at' => $startsAt,
            'service_ids' => $serviceIds,
            'staff_id' => $staffId,
            'service_name' => $serviceNames ?: implode(', ', array_map(fn ($id) => "Servizio #{$id}", $serviceIds)),
            'staff_name' => $staff ?? "Staff #{$staffId}",
        ];
        $state['awaiting_confirmation'] = true;

        return [
            'ok' => true,
            'selected' => $state['selected_slot'],
            'next_step' => 'Mostra riepilogo al cliente e chiedi conferma esplicita (sì/no) prima di chiamare book_appointment.',
        ];
    }

    private function bookAppointment(array $input, array &$state, int $businessId): array
    {
        if (! $this->isBookingEnabled($businessId)) {
            return ['ok' => false, 'code' => 'BOOKING_DISABLED', 'message' => 'La prenotazione via WhatsApp non è attiva per questo salone.'];
        }

        if (! ($state['awaiting_confirmation'] ?? false)) {
            return ['ok' => false, 'code' => 'CONFIRMATION_REQUIRED', 'message' => 'Il cliente non ha ancora confermato esplicitamente. Mostra il riepilogo e chiedi conferma prima di prenotare.'];
        }

        $slot = $state['selected_slot'] ?? null;

        if (! $slot) {
            return ['ok' => false, 'code' => 'SLOT_NOT_SELECTED', 'message' => 'Nessuno slot selezionato. Chiama select_slot prima di book_appointment.'];
        }

        // Validate that the staff_id passed by Claude matches the selected slot.
        // This catches cases where the customer changed staff after select_slot was called
        // and Claude hallucinated a confirmation without re-calling select_slot.
        $intendedStaffId = isset($input['staff_id']) ? (int) $input['staff_id'] : null;
        if ($intendedStaffId && $intendedStaffId !== (int) ($slot['staff_id'] ?? 0)) {
            $state['selected_slot'] = null;
            $state['awaiting_confirmation'] = false;

            return [
                'ok' => false,
                'code' => 'STAFF_MISMATCH',
                'message' => "Lo staff nel riepilogo (#{$intendedStaffId}) non corrisponde allo slot selezionato (#{$slot['staff_id']}). "
                    ."Il cliente ha cambiato preferenza: chiama list_available_slots con staff_id={$intendedStaffId}, poi select_slot con il nuovo orario.",
            ];
        }

        $generatedAt = $state['last_available_slots_generated_at'] ?? null;
        if (! $generatedAt || Carbon::parse($generatedAt)->diffInMinutes(now()) > 30) {
            $state['last_available_slots'] = [];
            $state['last_available_slots_generated_at'] = null;

            return ['ok' => false, 'code' => 'SLOTS_EXPIRED', 'message' => 'Gli slot proposti sono scaduti. Chiama list_available_slots di nuovo.', 'alternatives' => []];
        }

        $proposedStarts = collect($state['last_available_slots'])->pluck('starts_at')->toArray();
        if (! in_array($slot['starts_at'], $proposedStarts, true)) {
            return ['ok' => false, 'code' => 'SLOT_NO_LONGER_AVAILABLE', 'message' => 'Lo slot selezionato non è più disponibile.', 'alternatives' => []];
        }

        // Support both new (service_ids array) and legacy (service_id single) slot format
        $serviceIds = array_filter(array_map('intval', (array) ($slot['service_ids'] ?? (isset($slot['service_id']) ? [$slot['service_id']] : []))));
        $staffId = (int) ($slot['staff_id'] ?? 0);

        if (empty($serviceIds)) {
            return ['ok' => false, 'code' => 'MISSING_PARAMS', 'message' => 'service_ids mancanti nello slot selezionato.'];
        }

        $validCount = Service::where('business_id', $businessId)->whereIn('id', $serviceIds)->count();
        if ($validCount !== count($serviceIds)) {
            return ['ok' => false, 'code' => 'TENANT_MISMATCH', 'message' => 'Uno o più servizi non appartengono a questo salone.'];
        }

        if (! User::where('business_id', $businessId)->where('id', $staffId)->exists()) {
            return ['ok' => false, 'code' => 'TENANT_MISMATCH', 'message' => 'Staff non appartiene a questo salone.'];
        }

        try {
            $customerId = $state['customer_id'];
            if (! $customerId) {
                $name = $state['draft']['customer_name'] ?? 'Cliente WhatsApp';
                $user = $this->walkInService->createInlineCustomer($name, null, $businessId);
                $customerId = $user->id;
                $state['customer_id'] = $customerId;
            }

            $scheduledDate = Carbon::parse($slot['starts_at']);
            $appointment = $this->appointmentService->bookDirect([
                'userId' => $customerId,
                'serviceIds' => $serviceIds,
                'staffId' => $staffId,
                'scheduledDate' => $scheduledDate->toIso8601String(),
            ]);

            $state['step'] = 'booking_completed';
            $state['awaiting_confirmation'] = false;
            $state['selected_slot'] = null;

            return [
                'ok' => true,
                'appointment_id' => $appointment->id,
                'scheduled_at' => $scheduledDate->toIso8601String(),
                'service_name' => $slot['service_name'] ?? null,
                'staff_name' => $slot['staff_name'] ?? null,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'code' => 'SLOT_NO_LONGER_AVAILABLE', 'message' => $e->getMessage(), 'alternatives' => []];
        }
    }

    private function getNextAppointment(array &$state, int $businessId): array
    {
        $phone = $state['customer_phone'] ?? null;
        if (! $phone) {
            return ['ok' => false, 'code' => 'CUSTOMER_NOT_IDENTIFIED', 'message' => 'Numero di telefono non disponibile.', 'alternatives' => []];
        }

        $userId = UserPreference::withoutGlobalScope('business')
            ->where('phone_number', $phone)
            ->where('business_id', $businessId)
            ->value('user_id');
        if (! $userId) {
            return ['ok' => true, 'data' => ['appointment' => null]];
        }

        $appointment = Appointment::where('business_id', $businessId)
            ->where('user_id', $userId)
            ->upcoming()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_date')
            ->with(['staff'])
            ->first();

        if (! $appointment) {
            return ['ok' => true, 'data' => ['appointment' => null]];
        }

        return ['ok' => true, 'data' => ['appointment' => [
            'id' => $appointment->id,
            'scheduled_at' => $appointment->scheduled_date->toIso8601String(),
            'services' => $appointment->services->pluck('name'),
            'staff_name' => $appointment->staff?->name,
            'status' => $appointment->status,
        ]]];
    }

    private function cancelAppointment(array $input, array &$state, int $businessId): array
    {
        $setting = IntegrationSetting::where('business_id', $businessId)->first();
        if (! $setting?->isWhatsAppCancellationEnabled()) {
            return ['ok' => false, 'code' => 'CANCELLATION_DISABLED', 'message' => 'La cancellazione via WhatsApp non è attiva per questo salone.'];
        }

        $phone = $state['customer_phone'] ?? null;
        $userId = $phone
            ? UserPreference::withoutGlobalScope('business')
                ->where('phone_number', $phone)
                ->where('business_id', $businessId)
                ->value('user_id')
            : ($state['customer_id'] ?? null);

        if (! $userId) {
            return ['ok' => false, 'code' => 'CUSTOMER_NOT_IDENTIFIED', 'message' => 'Impossibile identificare il cliente.'];
        }

        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $appointment = Appointment::where('id', $appointmentId)
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->first();

        if (! $appointment) {
            return ['ok' => false, 'code' => 'APPOINTMENT_NOT_FOUND', 'message' => 'Appuntamento non trovato.'];
        }

        try {
            $this->appointmentService->cancelAppointment($appointment);

            return ['ok' => true, 'message' => 'Appuntamento cancellato.'];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'code' => 'CANCELLATION_FAILED', 'message' => $e->getMessage()];
        }
    }

    private function requestHumanHandoff(array $input, array &$state, int $businessId): array
    {
        $state['escalated'] = true;
        $state['escalated_at'] = now()->toIso8601String();
        $state['escalation_reason'] = $input['reason'] ?? 'Cliente ha richiesto assistenza umana.';
        $state['escalation_summary'] = $input['summary'] ?? null;

        $setting = IntegrationSetting::where('business_id', $businessId)->first();
        if ($email = $setting?->getWhatsAppAiHandoffEmail()) {
            $lastMessages = array_slice($state['messages'] ?? [], -5);
            $summary = $state['escalation_summary'] ?? 'Nessun riepilogo disponibile.';
            $phone = $state['customer_phone'] ?? 'Sconosciuto';
            $reason = $state['escalation_reason'] ?? 'Non specificato';
            $body = "Richiesta di assistenza da: {$phone}\n\nMotivo: {$reason}\n\nRiepilogo: {$summary}\n\nUltimi messaggi:\n".json_encode($lastMessages, JSON_PRETTY_PRINT);
            dispatch(function () use ($email, $body) {
                Mail::raw($body, fn ($m) => $m->to($email)->subject('Richiesta assistenza WhatsApp'));
            })->afterCommit();
        }

        return ['ok' => true, 'message' => 'Escalation attivata. Il salone sarà notificato.'];
    }

    private function isBookingEnabled(int $businessId): bool
    {
        $setting = IntegrationSetting::where('business_id', $businessId)->first();

        return $setting?->isWhatsAppBookingEnabled() ?? true;
    }

    /**
     * Tools exposed to Claude: only safe non-booking operations.
     * Booking and cancellation state transitions are handled by PHP directly.
     */
    public function getNonBookingToolDefinitions(IntegrationSetting $setting): array
    {
        return [
            [
                'name' => 'get_next_appointment',
                'description' => 'Recupera il prossimo appuntamento del cliente.',
                'input_schema' => ['type' => 'object', 'properties' => (object) [], 'required' => []],
            ],
            [
                'name' => 'request_human_handoff',
                'description' => 'Attiva escalation umana. Usare se il cliente è frustrato o il bot non riesce a gestire la richiesta.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'reason' => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
        ];
    }

}
