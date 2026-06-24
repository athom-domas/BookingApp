<?php
namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppMessageStatus;
use App\Services\PhoneNormalizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.whatsapp.webhook_verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $rawBody = $request->getContent();
        $this->verifySignature($rawBody, $request->header('X-Hub-Signature-256', ''));

        $payload = $request->all();

        foreach (data_get($payload, 'entry', []) as $entry) {
            foreach (data_get($entry, 'changes', []) as $change) {
                $value         = data_get($change, 'value', []);
                $phoneNumberId = data_get($value, 'metadata.phone_number_id');

                $setting = $this->resolveBusinessSetting($phoneNumberId);
                if ($setting === null) {
                    continue;
                }

                foreach (data_get($value, 'statuses', []) as $statusData) {
                    $this->saveStatus($statusData);
                }

                foreach (data_get($value, 'messages', []) as $messageData) {
                    $this->processMessage($messageData, $value, $setting);
                }
            }
        }

        return response('', 200);
    }

    private function verifySignature(string $rawBody, string $header): void
    {
        $appSecret = config('services.whatsapp.app_secret');
        if (! $appSecret) {
            return;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);
        if (! hash_equals($expected, $header)) {
            abort(403, 'Invalid signature');
        }
    }

    private function resolveBusinessSetting(string $phoneNumberId): ?IntegrationSetting
    {
        $cacheKey   = "whatsapp:phone_number:{$phoneNumberId}:business_id";
        $businessId = cache()->remember($cacheKey, 3600, function () use ($phoneNumberId) {
            return IntegrationSetting::findByPhoneNumberId($phoneNumberId)?->business_id;
        });

        if (! $businessId) {
            Log::critical('WhatsApp webhook from unknown phone_number_id', ['phone_number_id' => $phoneNumberId]);
            return null;
        }

        return IntegrationSetting::where('business_id', $businessId)->first();
    }

    private function saveStatus(array $statusData): void
    {
        try {
            WhatsAppMessageStatus::create([
                'provider_message_id' => $statusData['id'] ?? '',
                'status'              => $statusData['status'] ?? 'sent',
                'payload'             => $statusData,
                'occurred_at'         => isset($statusData['timestamp'])
                    ? \Carbon\Carbon::createFromTimestamp($statusData['timestamp'])
                    : null,
            ]);
        } catch (\Illuminate\Database\QueryException) {
            // Duplicate status event — swallowed silently
        }
    }

    private function processMessage(array $messageData, array $value, IntegrationSetting $setting): void
    {
        $wamid   = $messageData['id'] ?? null;
        $waId    = $messageData['from'] ?? '';
        $phone   = PhoneNormalizer::normalize('+' . ltrim($waId, '+'));
        $profile = collect(data_get($value, 'contacts', []))->firstWhere('wa_id', $waId);

        if ($wamid && WhatsAppMessage::findByWamid($wamid)) {
            return;
        }

        $message = WhatsAppMessage::create([
            'business_id'      => $setting->business_id,
            'wamid'            => $wamid,
            'phone'            => '+' . ltrim($waId, '+'),
            'phone_normalized' => $phone,
            'wa_id'            => $waId,
            'profile_name'     => data_get($profile, 'profile.name'),
            'direction'        => 'inbound',
            'type'             => $messageData['type'] ?? 'text',
            'payload'          => $messageData,
        ]);

        if (! $setting->hasWhatsAppAiEnabled()) {
            return;
        }

        ProcessWhatsAppMessageJob::dispatch($message->id, $setting->business_id);
    }
}
