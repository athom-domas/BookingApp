<?php

namespace App\Services;

use App\Exceptions\WhatsAppWindowExpiredException;
use App\Models\IntegrationSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private function getSettings(int $businessId): IntegrationSetting
    {
        return IntegrationSetting::withoutGlobalScope('business')
            ->where('business_id', $businessId)
            ->firstOrNew(['business_id' => $businessId]);
    }

    private function graphUrl(string $phoneId, string $path = 'messages'): string
    {
        $version = config('services.whatsapp.graph_api_version', 'v23.0');
        return "https://graph.facebook.com/{$version}/{$phoneId}/{$path}";
    }

    private function normalizePhoneForApi(string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($digits, '0')) {
            $digits = '39' . ltrim($digits, '0');
        }
        return $digits;
    }

    public function sendTextWithinWindow(string $phone, string $text, Carbon $lastUserMessageAt, int $businessId): bool
    {
        if (now()->diffInSeconds($lastUserMessageAt, false) <= -86400) {
            throw new WhatsAppWindowExpiredException($phone);
        }

        $setting = $this->getSettings($businessId);
        $token   = $setting->meta_whatsapp_token;
        $phoneId = $setting->meta_whatsapp_phone_id;

        if (! $token || ! $phoneId) {
            return false;
        }

        $response = Http::withToken($token)
            ->post($this->graphUrl($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $this->normalizePhoneForApi($phone),
                'type'              => 'text',
                'text'              => ['body' => $text],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp sendText error', ['status' => $response->status(), 'body' => $response->json()]);
            return false;
        }

        Log::info('WhatsApp sendText ok', ['to' => $phone, 'status' => $response->status(), 'wamid' => $response->json('messages.0.id')]);
        return true;
    }

    public function sendTemplate(string $phone, string $templateName, string $language, string $category, array $params, int $businessId): ?string
    {
        $setting = $this->getSettings($businessId);
        $token   = $setting->meta_whatsapp_token;
        $phoneId = $setting->meta_whatsapp_phone_id;

        if (! $token || ! $phoneId) {
            return null;
        }

        $response = Http::withToken($token)
            ->post($this->graphUrl($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $this->normalizePhoneForApi($phone),
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => $p], $params),
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp sendTemplate error', ['status' => $response->status(), 'body' => $response->json()]);
            return null;
        }

        return $response->json('messages.0.id', '');
    }
}
