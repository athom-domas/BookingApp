<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\IntegrationSetting;
use App\Models\Service;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\Booking\AppointmentService;
use App\Services\Booking\SlotCalculationService;
use App\Services\WalkInService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class WhatsAppToolDispatcher
{
    private array $whitelist = [
        'list_services', 'list_staff_for_service', 'list_available_slots',
        'book_appointment', 'get_next_appointment', 'cancel_appointment',
        'request_human_handoff',
    ];

    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly SlotCalculationService $slotService,
        private readonly WalkInService $walkInService,
    ) {}

    public function dispatch(array $toolCall, array &$state, int $businessId): array
    {
        $name  = $toolCall['name'] ?? '';
        $input = $toolCall['input'] ?? [];

        if (! in_array($name, $this->whitelist, true)) {
            return ['ok' => false, 'code' => 'SERVICE_NOT_FOUND', 'message' => "Tool '{$name}' not allowed."];
        }

        return match ($name) {
            'list_services'          => $this->listServices($businessId),
            'list_staff_for_service' => $this->listStaffForService($input, $businessId),
            'list_available_slots'   => $this->listAvailableSlots($input, $state, $businessId),
            'book_appointment'       => $this->bookAppointment($state, $businessId),
            'get_next_appointment'   => $this->getNextAppointment($state, $businessId),
            'cancel_appointment'     => $this->cancelAppointment($input, $state, $businessId),
            'request_human_handoff'  => $this->requestHumanHandoff($input, $state, $businessId),
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
        $service   = Service::where('business_id', $businessId)->where('id', $serviceId)->first();

        if (! $service) {
            return ['ok' => false, 'code' => 'SERVICE_NOT_FOUND', 'message' => 'Servizio non trovato.'];
        }

        $staff = $service->staff()->where('users.business_id', $businessId)->get(['users.id', 'users.name']);
        return ['ok' => true, 'staff' => $staff->toArray()];
    }

    private function listAvailableSlots(array $input, array &$state, int $businessId): array
    {
        $serviceIds = array_map('intval', (array) ($input['service_ids'] ?? []));
        $staffId    = isset($input['staff_id']) ? (int) $input['staff_id'] : null;
        $date       = $input['date'] ?? null;

        if (empty($serviceIds) || ! $date) {
            return ['ok' => false, 'code' => 'MISSING_CONFIRMATION', 'message' => 'service_ids e date sono obbligatori.'];
        }

        $slots = $this->slotService->getAvailableSlots([
            'date'            => $date,
            'serviceIds'      => $serviceIds,
            'staffId'         => $staffId,
            'staffPreference' => $staffId ? 'specific' : 'any',
        ]);

        $state['last_available_slots']              = $slots;
        $state['last_available_slots_generated_at'] = now()->toIso8601String();

        return ['ok' => true, 'slots' => $slots];
    }

    private function bookAppointment(array &$state, int $businessId): array
    {
        if (! $state['awaiting_confirmation']) {
            return ['ok' => false, 'code' => 'CONFIRMATION_REQUIRED', 'message' => 'Il cliente non ha ancora confermato.'];
        }

        $slot = $state['selected_slot'] ?? null;
        if (! $slot) {
            return ['ok' => false, 'code' => 'MISSING_CONFIRMATION', 'message' => 'Nessuno slot selezionato.'];
        }

        $generatedAt = $state['last_available_slots_generated_at'] ?? null;
        if (! $generatedAt || Carbon::parse($generatedAt)->diffInMinutes(now()) > 15) {
            $state['last_available_slots']              = [];
            $state['last_available_slots_generated_at'] = null;
            return ['ok' => false, 'code' => 'SLOTS_EXPIRED', 'message' => 'Gli slot proposti sono scaduti. Richiedi nuovi slot.', 'alternatives' => []];
        }

        $proposedStarts = collect($state['last_available_slots'])->pluck('starts_at')->toArray();
        if (! in_array($slot['starts_at'], $proposedStarts, true)) {
            return ['ok' => false, 'code' => 'SLOT_NO_LONGER_AVAILABLE', 'message' => 'Lo slot selezionato non è tra quelli proposti.', 'alternatives' => []];
        }

        $serviceId = (int) ($slot['service_id'] ?? 0);
        $staffId   = (int) ($slot['staff_id'] ?? 0);

        if (! Service::where('business_id', $businessId)->where('id', $serviceId)->exists()) {
            return ['ok' => false, 'code' => 'TENANT_MISMATCH', 'message' => 'Servizio non appartiene a questo salone.'];
        }

        if (! User::where('business_id', $businessId)->where('id', $staffId)->exists()) {
            return ['ok' => false, 'code' => 'TENANT_MISMATCH', 'message' => 'Staff non appartiene a questo salone.'];
        }

        $customerId = $state['customer_id'];
        if (! $customerId) {
            $name       = $state['draft']['customer_name'] ?? 'Cliente WhatsApp';
            $user       = $this->walkInService->createInlineCustomer($name, null, $businessId);
            $customerId = $user->id;
            $state['customer_id'] = $customerId;
        }

        try {
            $scheduledDate = Carbon::parse($slot['starts_at']);
            $appointment   = $this->appointmentService->bookDirect([
                'userId'        => $customerId,
                'serviceIds'    => [$serviceId],
                'staffId'       => $staffId,
                'scheduledDate' => $scheduledDate->toIso8601String(),
            ]);

            $state['step']                  = 'booking_completed';
            $state['awaiting_confirmation'] = false;
            $state['selected_slot']         = null;

            return [
                'ok'             => true,
                'appointment_id' => $appointment->id,
                'scheduled_at'   => $scheduledDate->toIso8601String(),
            ];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'code' => 'SLOT_NO_LONGER_AVAILABLE', 'message' => $e->getMessage(), 'alternatives' => []];
        }
    }

    private function getNextAppointment(array &$state, int $businessId): array
    {
        $phone = $state['customer_phone'] ?? null;
        if (! $phone) {
            return ['ok' => false, 'code' => 'MISSING_CONFIRMATION', 'message' => 'Numero di telefono non disponibile.', 'alternatives' => []];
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
            ->first();

        if (! $appointment) {
            return ['ok' => true, 'data' => ['appointment' => null]];
        }

        return ['ok' => true, 'data' => ['appointment' => [
            'id'             => $appointment->id,
            'scheduled_at'   => $appointment->scheduled_date->toIso8601String(),
            'services'       => $appointment->services->pluck('name'),
            'staff_name'     => $appointment->staff?->name,
            'status'         => $appointment->status,
        ]]];
    }

    private function cancelAppointment(array $input, array &$state, int $businessId): array
    {
        $setting = IntegrationSetting::where('business_id', $businessId)->first();
        if (! $setting?->isWhatsAppCancellationEnabled()) {
            return ['ok' => false, 'code' => 'CANCELLATION_DISABLED', 'message' => 'La cancellazione via WhatsApp non è attiva per questo salone.'];
        }

        $appointmentId = (int) ($input['appointment_id'] ?? 0);
        $appointment   = Appointment::where('id', $appointmentId)->where('business_id', $businessId)->first();

        if (! $appointment) {
            return ['ok' => false, 'code' => 'MISSING_CONFIRMATION', 'message' => 'Appuntamento non trovato.'];
        }

        try {
            $this->appointmentService->cancelAppointment($appointment);
            return ['ok' => true, 'message' => 'Appuntamento cancellato.'];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'code' => 'MISSING_CONFIRMATION', 'message' => $e->getMessage()];
        }
    }

    private function requestHumanHandoff(array $input, array &$state, int $businessId): array
    {
        $state['escalated']         = true;
        $state['escalated_at']      = now()->toIso8601String();
        $state['escalation_reason'] = $input['reason'] ?? 'Cliente ha richiesto assistenza umana.';
        $state['escalation_summary'] = $input['summary'] ?? null;

        $setting = IntegrationSetting::where('business_id', $businessId)->first();
        if ($email = $setting?->getWhatsAppAiHandoffEmail()) {
            $lastMessages = array_slice($state['messages'] ?? [], -5);
            $summary      = $state['escalation_summary'] ?? 'Nessun riepilogo disponibile.';
            $phone        = $state['customer_phone'] ?? 'Sconosciuto';
            $reason       = $state['escalation_reason'] ?? 'Non specificato';
            $body         = "Richiesta di assistenza da: {$phone}\n\nMotivo: {$reason}\n\nRiepilogo: {$summary}\n\nUltimi messaggi:\n" . json_encode($lastMessages, JSON_PRETTY_PRINT);
            \Illuminate\Support\Facades\Mail::raw($body, function ($m) use ($email) {
                $m->to($email)->subject('Richiesta assistenza WhatsApp');
            });
        }

        return ['ok' => true, 'message' => 'Escalation attivata. Il salone sarà notificato.'];
    }

    public function getToolDefinitions(IntegrationSetting $setting): array
    {
        $tools = [
            [
                'name'         => 'list_services',
                'description'  => 'Elenca i servizi attivi del salone.',
                'input_schema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
            ],
            [
                'name'         => 'list_staff_for_service',
                'description'  => 'Elenca lo staff che eroga un determinato servizio.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => ['service_id' => ['type' => 'integer', 'description' => 'ID del servizio']],
                    'required'   => ['service_id'],
                ],
            ],
            [
                'name'         => 'list_available_slots',
                'description'  => 'Restituisce gli slot disponibili per un servizio e una data. Salva i risultati internamente per la conferma.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'service_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                        'date'        => ['type' => 'string', 'description' => 'Data in formato YYYY-MM-DD'],
                        'staff_id'    => ['type' => 'integer', 'description' => 'Opzionale: ID staff preferito'],
                    ],
                    'required' => ['service_ids', 'date'],
                ],
            ],
            [
                'name'         => 'book_appointment',
                'description'  => 'Prenota lo slot confermato dal cliente. Usare solo quando awaiting_confirmation=true e il cliente ha confermato.',
                'input_schema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
            ],
            [
                'name'         => 'get_next_appointment',
                'description'  => 'Recupera il prossimo appuntamento del cliente.',
                'input_schema' => ['type' => 'object', 'properties' => (object)[], 'required' => []],
            ],
            [
                'name'         => 'request_human_handoff',
                'description'  => 'Attiva escalation umana. Usare se il cliente è frustrato o il bot non riesce a gestire la richiesta.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => [
                        'reason'  => ['type' => 'string'],
                        'summary' => ['type' => 'string'],
                    ],
                    'required' => [],
                ],
            ],
        ];

        if ($setting->isWhatsAppCancellationEnabled()) {
            $tools[] = [
                'name'         => 'cancel_appointment',
                'description'  => 'Cancella un appuntamento futuro del cliente.',
                'input_schema' => [
                    'type'       => 'object',
                    'properties' => ['appointment_id' => ['type' => 'integer']],
                    'required'   => ['appointment_id'],
                ],
            ];
        }

        return $tools;
    }
}
