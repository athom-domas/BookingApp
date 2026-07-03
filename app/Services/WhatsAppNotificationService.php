<?php

namespace App\Services;

use App\Jobs\SendWhatsAppNotificationJob;
use App\Models\Appointment;
use App\Models\IntegrationSetting;
use App\Models\WhatsAppMessage;
use Illuminate\Support\Facades\Log;

class WhatsAppNotificationService
{
    public function dispatchForAppointment(Appointment $appointment, string $templateName, array $parameters): ?WhatsAppMessage
    {
        $settings = IntegrationSetting::withoutGlobalScope('business')
            ->where('business_id', $appointment->business_id)
            ->first();

        if (! $settings?->hasWhatsAppNotificationsEnabled()) {
            return null;
        }

        if (empty($settings->meta_whatsapp_token) || empty($settings->meta_whatsapp_phone_id)) {
            return null;
        }

        if (! $settings->hasWhatsAppMonthlyCapacity()) {
            Log::info('WhatsApp monthly limit reached', ['business_id' => $appointment->business_id]);
            return null;
        }

        $prefs = $appointment->user?->preferences;

        if ($prefs?->notification_channel !== 'whatsapp' || empty($prefs->phone_number)) {
            return null;
        }

        $alreadySent = WhatsAppMessage::where('business_id', $appointment->business_id)
            ->forAppointmentTemplate($appointment->id, $templateName)
            ->whereIn('status', ['queued', 'sent'])
            ->exists();

        if ($alreadySent) {
            return null;
        }

        $message = WhatsAppMessage::create([
            'business_id'      => $appointment->business_id,
            'appointment_id'   => $appointment->id,
            'phone'            => $prefs->phone_number,
            'phone_normalized' => PhoneNormalizer::normalize($prefs->phone_number),
            'direction'        => 'outbound',
            'type'             => 'template',
            'template_name'    => $templateName,
            'payload'          => ['parameters' => $parameters],
            'status'           => 'queued',
        ]);

        SendWhatsAppNotificationJob::dispatch($message->id)->afterCommit();

        return $message;
    }

    public static function appointmentParams(Appointment $appointment): array
    {
        return [
            $appointment->user->name,
            $appointment->services_label,
            $appointment->scheduled_date->format('d/m/Y'),
            $appointment->scheduled_date->format('H:i'),
            $appointment->staff->name,
        ];
    }
}
