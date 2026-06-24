<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WhatsAppConversationState
{
    private function draftKey(int $businessId, string $phone): string
    {
        return "whatsapp:conv:{$businessId}:{$phone}";
    }

    private function summaryKey(int $businessId, string $phone): string
    {
        return "whatsapp:summary:{$businessId}:{$phone}";
    }

    private function lockKey(int $businessId, string $phone): string
    {
        return "whatsapp:conv:lock:{$businessId}:{$phone}";
    }

    public function get(int $businessId, string $phone): array
    {
        $draftTtl   = config('services.whatsapp.conversation_ttl', 4) * 3600;
        $summaryTtl = config('services.whatsapp.summary_ttl', 24) * 3600;

        $state = Cache::get($this->draftKey($businessId, $phone));

        if ($state === null) {
            $summary = Cache::get($this->summaryKey($businessId, $phone));
            $state   = $this->fresh($phone);
            if ($summary) {
                $state['summary'] = $summary;
            }
            Cache::put($this->draftKey($businessId, $phone), $state, $draftTtl);
        }

        return $state;
    }

    public function set(int $businessId, string $phone, array $state): void
    {
        $draftTtl   = config('services.whatsapp.conversation_ttl', 4) * 3600;
        $summaryTtl = config('services.whatsapp.summary_ttl', 24) * 3600;

        if (isset($state['messages']) && count($state['messages']) > 15) {
            $state['messages'] = array_slice($state['messages'], -15);
        }

        if (! empty($state['summary'])) {
            Cache::put($this->summaryKey($businessId, $phone), $state['summary'], $summaryTtl);
        }

        Cache::put($this->draftKey($businessId, $phone), $state, $draftTtl);
    }

    public function withLock(int $businessId, string $phone, callable $fn): mixed
    {
        return Cache::lock($this->lockKey($businessId, $phone), 90)
            ->block(10, $fn);
    }

    public function fresh(string $phone, string $waId = ''): array
    {
        return [
            'intent'                            => 'unknown',
            'step'                              => 'new',
            'language'                          => 'it',
            'customer_phone'                    => $phone,
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
        ];
    }
}
