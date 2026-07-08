<?php

namespace App\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsAppConversationState
{
    private function draftKey(int $businessId, string $phoneNormalized): string
    {
        return "whatsapp:conv:{$businessId}:{$phoneNormalized}";
    }

    private function summaryKey(int $businessId, string $phoneNormalized): string
    {
        return "whatsapp:summary:{$businessId}:{$phoneNormalized}";
    }

    private function lockKey(int $businessId, string $phoneNormalized): string
    {
        return "whatsapp:conv:lock:{$businessId}:{$phoneNormalized}";
    }

    public function get(int $businessId, string $phoneNormalized): array
    {
        $draftTtl = config('services.whatsapp.conversation_ttl', 4) * 3600;
        $summaryTtl = config('services.whatsapp.summary_ttl', 24) * 3600;

        $state = Cache::get($this->draftKey($businessId, $phoneNormalized));

        if ($state === null) {
            $summary = Cache::get($this->summaryKey($businessId, $phoneNormalized));
            $state = $this->fresh($phoneNormalized);
            if ($summary) {
                $state['summary'] = $summary;
            }
            Cache::put($this->draftKey($businessId, $phoneNormalized), $state, $draftTtl);
        } else {
            $normalized = $this->normalize($state, $phoneNormalized);
            if ($normalized !== $state) {
                $state = $normalized;
                Cache::put($this->draftKey($businessId, $phoneNormalized), $state, $draftTtl);
            }
        }

        return $state;
    }

    public function set(int $businessId, string $phoneNormalized, array $state): void
    {
        $draftTtl = config('services.whatsapp.conversation_ttl', 4) * 3600;
        $summaryTtl = config('services.whatsapp.summary_ttl', 24) * 3600;

        if (isset($state['messages']) && count($state['messages']) > 15) {
            $state['messages'] = array_slice($state['messages'], -15);
        }

        if (! empty($state['summary'])) {
            Cache::put($this->summaryKey($businessId, $phoneNormalized), $state['summary'], $summaryTtl);
        }

        Cache::put($this->draftKey($businessId, $phoneNormalized), $state, $draftTtl);
    }

    public function withLock(int $businessId, string $phoneNormalized, callable $fn): mixed
    {
        $lock = Cache::lock($this->lockKey($businessId, $phoneNormalized), 90);
        try {
            return $lock->block(10, $fn);
        } catch (LockTimeoutException) {
            Log::warning('WhatsApp conversation lock timeout', [
                'business_id' => $businessId,
                'phone_normalized' => $phoneNormalized,
            ]);

            return null;
        }
    }

    public function fresh(string $phoneNormalized, string $waId = ''): array
    {
        return [
            'intent' => 'unknown',
            'step' => 'new',
            'language' => 'it',
            'customer_phone' => $phoneNormalized,
            'wa_id' => $waId,
            'customer_id' => null,
            'conversation_id' => (string) Str::ulid(),
            'messages' => [],
            'summary' => null,
            'draft' => [
                'service_ids' => [],
                'service_id' => null,
                'staff_id' => null,
                'staff_any' => false,
                'date' => null,
                'time' => null,
                'time_period' => null,
                'time_any' => false,
                'customer_name' => null,
            ],
            'last_available_slots' => [],
            'last_available_slots_generated_at' => null,
            'last_available_slots_service_ids' => [],
            'last_service_options' => [],
            'selected_slot' => null,
            'confirmation_token' => null,
            'pending_appointment_id' => null,
            'pending_appointment_user_id' => null,
            'pending_appointment_amount_cents' => null,
            'pending_appointment_details' => [],
            'pending_cancellation_appointment_id' => null,
            'pending_cancellation_details' => [],
            'last_user_message_at' => null,
            'awaiting_confirmation' => false,
            'escalated' => false,
            'escalated_at' => null,
            'escalation_reason' => null,
            'escalation_summary' => null,
            'last_tool_call' => null,
            'error_count' => 0,
            'turn_count' => 0,
        ];
    }

    private function normalize(array $state, string $phoneNormalized): array
    {
        $fresh = $this->fresh($phoneNormalized, (string) ($state['wa_id'] ?? ''));
        $draft = is_array($state['draft'] ?? null) ? $state['draft'] : [];

        $state = array_replace($fresh, $state);
        $state['draft'] = array_replace($fresh['draft'], $draft);

        $validSteps = [
            'new',
            'idle',
            'collecting',
            'slots_shown',
            'slot_confirmed',
            'awaiting_payment_choice',
            'awaiting_cancellation_confirmation',
            'booking_completed',
        ];

        if (! in_array($state['step'] ?? null, $validSteps, true)) {
            $state['step'] = 'idle';
        }

        if (empty($state['draft']['service_ids']) && ! empty($state['draft']['service_id'])) {
            $state['draft']['service_ids'] = [(int) $state['draft']['service_id']];
        }
        $state['draft']['service_ids'] = array_values(array_filter(array_map('intval', (array) $state['draft']['service_ids'])));
        $state['draft']['service_id'] = $state['draft']['service_ids'][0] ?? null;

        if (! is_array($state['messages'])) {
            $state['messages'] = [];
        }
        if (! is_array($state['last_available_slots'])) {
            $state['last_available_slots'] = [];
        }
        if (! is_array($state['last_service_options'] ?? null)) {
            $state['last_service_options'] = [];
        }
        if (! is_array($state['pending_appointment_details'] ?? null)) {
            $state['pending_appointment_details'] = [];
        }
        if (! is_array($state['pending_cancellation_details'] ?? null)) {
            $state['pending_cancellation_details'] = [];
        }

        if (! is_array($state['last_available_slots_service_ids'] ?? null)) {
            $state['last_available_slots_service_ids'] = [];
        }
        $state['last_available_slots_service_ids'] = array_values(array_filter(array_map('intval', $state['last_available_slots_service_ids'])));

        if (is_array($state['selected_slot'] ?? null)) {
            if (empty($state['selected_slot']['service_ids']) && ! empty($state['selected_slot']['service_id'])) {
                $state['selected_slot']['service_ids'] = [(int) $state['selected_slot']['service_id']];
            }
            $state['selected_slot']['service_ids'] = array_values(array_filter(array_map('intval', (array) ($state['selected_slot']['service_ids'] ?? []))));
        } else {
            $state['selected_slot'] = null;
        }

        if (empty($state['last_available_slots_service_ids'])) {
            $state['last_available_slots_service_ids'] = $state['selected_slot']['service_ids']
                ?? $state['draft']['service_ids']
                ?? [];
        }

        return $state;
    }
}
