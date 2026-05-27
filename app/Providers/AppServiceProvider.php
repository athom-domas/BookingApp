<?php

namespace App\Providers;

use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Booking\SlotCalculationService::class);
        $this->app->singleton(\App\Services\Booking\OperatorScoringService::class);
        $this->app->singleton(\App\Services\Booking\AppointmentService::class);

        $this->app->singleton(PaymentService::class, function () {
            $secret = \App\Models\IntegrationSetting::getStripeSecretKey() ?? config('services.stripe.secret');
            return new PaymentService(new StripeClient($secret));
        });

        $this->app->singleton(\App\Services\NotificationService::class, function () {
            $sid   = \App\Models\IntegrationSetting::getTwilioSid()   ?? config('services.twilio.sid');
            $token = \App\Models\IntegrationSetting::getTwilioToken() ?? config('services.twilio.token');
            $client = new \Twilio\Rest\Client($sid, $token);
            return new \App\Services\NotificationService($client->messages);
        });

        $this->app->singleton(\App\Services\GoogleCalendarService::class, function () {
            $client = new \Google\Client();
            $credJson = \App\Models\IntegrationSetting::getGoogleCredentialsJson();
            if ($credJson) {
                $client->setAuthConfig(json_decode($credJson, true));
            } else {
                $credPath = config('services.google.credentials');
                if (file_exists($credPath)) {
                    $client->setAuthConfig($credPath);
                }
            }
            $client->addScope(\Google\Service\Calendar::CALENDAR);
            return new \App\Services\GoogleCalendarService(
                new \Google\Service\Calendar($client)
            );
        });
    }

    public function boot(): void
    {
        Carbon::setLocale('it');
    }
}
