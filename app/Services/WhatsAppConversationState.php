<?php

namespace App\Services;

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
        $draftTtl   = config('services.whatsapp.conversation_ttl', 4) * 3600;
        $summaryTtl = config('services.whatsapp.summary_ttl', 24) * 3600;

        $state = Cache::get($this->draftKey($businessId, $phoneNormalized));

        if ($state === null) {
            $summary = Cache::get($this->summaryKey($businessId, $phoneNormalized));
            $state   = $this->fresh($phoneNormalized);
            if ($summary) {
                $state['summary'] = $summary;
            }
            Cache::put($this->draftKey($businessId, $phoneNormalized), $state, $draftTtl);
        }

        return $state;
    }

    public function set(int $businessId, string $phoneNormalized, array $state): void
    {
        $draftTtl   = config('services.whatsapp.conversation_ttl', 4) * 3600;
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
        } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
            Log::warning('WhatsApp conversation lock timeout', [
                'business_id'      => $businessId,
                'phone_normalized' => $phoneNormalized,
            ]);
            return null;
        }
    }

    public function fresh(string $phoneNormalized, string $waId = ''): array
    {
        return [
            'intent'                            => 'unknown',
            'step'                              => 'new',
            'language'                          => 'it',
            'customer_phone'                    => $phoneNormalized,
            'wa_id'                             => $waId,
            'customer_id'                       => null,
            'conversation_id'                   => (string) Str::ulid(),
            'messages'                          => [],
            'summary'                           => null,
            'draft'                             => [
                'service_id'    => null,
                'staff_id'      => null,
                'date'          => null,
                'time'          => null,
                'customer_name' => null,
            ],
            'last_available_slots'              => [],
            'last_available_slots_generated_at' => null,
            'selected_slot'                     => null,
            'confirmation_token'                => null,
            'last_user_message_at'              => null,
            'awaiting_confirmation'             => false,
            'escalated'                         => false,
            'escalated_at'                      => null,
            'escalation_reason'                 => null,
            'escalation_summary'                => null,
            'last_tool_call'                    => null,
            'error_count'                       => 0,
            'turn_count'                        => 0,
        ];
    }
}
