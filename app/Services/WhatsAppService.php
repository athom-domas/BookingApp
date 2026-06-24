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
        return IntegrationSetting::where('business_id', $businessId)->firstOrNew(['business_id' => $businessId]);
    }

    private function graphUrl(string $phoneId, string $path = 'messages'): string
    {
        $version = config('services.whatsapp.graph_api_version', 'v23.0');
        return "https://graph.facebook.com/{$version}/{$phoneId}/{$path}";
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
                'to'                => ltrim(preg_replace('/[^0-9+]/', '', $phone), '+'),
                'type'              => 'text',
                'text'              => ['body' => $text],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp sendText error', ['status' => $response->status(), 'body' => $response->json()]);
            return false;
        }

        return true;
    }

    public function sendTemplate(string $phone, string $templateName, string $language, string $category, array $params, int $businessId): bool
    {
        $setting = $this->getSettings($businessId);
        $token   = $setting->meta_whatsapp_token;
        $phoneId = $setting->meta_whatsapp_phone_id;

        if (! $token || ! $phoneId) {
            return false;
        }

        $number = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($number, '0')) {
            $number = '39' . ltrim($number, '0');
        }

        $response = Http::withToken($token)
            ->post($this->graphUrl($phoneId), [
                'messaging_product' => 'whatsapp',
                'to'                => $number,
                'type'              => 'template',
                'template'          => [
                    'name'       => $templateName,
                    'language'   => ['code' => $language],
                    'category'   => $category,
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
            return false;
        }

        return true;
    }

    public function sendTemplateDefault(string $phone, array $parameters): bool
    {
        $setting  = IntegrationSetting::current();
        $template = $setting->meta_whatsapp_template ?? 'appointment_reminder';
        $businessId = $setting->business_id ?? 0;

        return $this->sendTemplate($phone, $template, 'it', 'UTILITY', $parameters, $businessId);
    }
}
