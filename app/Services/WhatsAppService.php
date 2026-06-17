<?php

namespace App\Services;

use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    public function sendTemplate(string $phone, array $parameters): bool
    {
        $token    = IntegrationSetting::getMetaWhatsAppToken();
        $phoneId  = IntegrationSetting::getMetaWhatsAppPhoneId();
        $template = IntegrationSetting::getMetaWhatsAppTemplate();

        if (! $token || ! $phoneId) {
            return false;
        }

        $number = preg_replace('/[^0-9]/', '', $phone);
        if (str_starts_with($number, '0')) {
            $number = '39' . ltrim($number, '0');
        }

        $response = Http::withToken($token)
            ->post("https://graph.facebook.com/v19.0/{$phoneId}/messages", [
                'messaging_product' => 'whatsapp',
                'to'                => $number,
                'type'              => 'template',
                'template'          => [
                    'name'       => $template,
                    'language'   => ['code' => 'it'],
                    'components' => [
                        [
                            'type'       => 'body',
                            'parameters' => array_map(
                                fn ($p) => ['type' => 'text', 'text' => $p],
                                $parameters
                            ),
                        ],
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::error('WhatsApp Meta API error', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);
            return false;
        }

        return true;
    }
}
