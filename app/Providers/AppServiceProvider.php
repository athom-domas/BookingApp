<?php

namespace App\Providers;

use App\Services\PaymentService;
use Carbon\Carbon;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(\App\Services\Booking\SlotCalculationService::class);
        $this->app->singleton(\App\Services\Booking\OperatorScoringService::class);
        $this->app->singleton(\App\Services\Booking\AppointmentService::class);

        $this->app->bind(StripeClient::class, function () {
            try {
                $secret = \App\Models\IntegrationSetting::getStripeSecretKey();
            } catch (\Throwable) {
                $secret = null;
            }
            $secret ??= config('services.stripe.secret');
            $secret ??= config('cashier.secret');
            if (empty($secret)) {
                return null;
            }
            return new StripeClient($secret);
        });

        $this->app->bind('platform.stripe', function () {
            $secret = config('services.stripe.secret');
            if (empty($secret)) {
                return null;
            }
            return new StripeClient($secret);
        });

        $this->app->bind(PaymentService::class, function ($app) {
            return new PaymentService(
                $app->make(StripeClient::class),
                $app->make(\App\Services\StripeConnectService::class),
            );
        });

        $this->app->bind(\App\Services\StripeConnectService::class, function ($app) {
            return new \App\Services\StripeConnectService($app->make('platform.stripe'));
        });

        $this->app->bind(\App\Services\RefundService::class, function ($app) {
            return new \App\Services\RefundService($app->make('platform.stripe'));
        });

        $this->app->bind(\App\Services\NotificationService::class, function () {
            $sid   = \App\Models\IntegrationSetting::getTwilioSid()   ?? config('services.twilio.sid');
            $token = \App\Models\IntegrationSetting::getTwilioToken() ?? config('services.twilio.token');
            if (empty($sid) || empty($token)) {
                return new \App\Services\NotificationService(null);
            }
            $client = new \Twilio\Rest\Client($sid, $token);
            return new \App\Services\NotificationService($client->messages);
        });

        $this->app->bind(\App\Services\GoogleCalendarService::class, function () {
            $client = new \Google\Client();
            $credJson = \App\Models\IntegrationSetting::getGoogleCredentialsJson();
            if ($credJson) {
                $decoded = json_decode($credJson, true);
                if ($decoded !== null) {
                    $client->setAuthConfig($decoded);
                }
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
        Cashier::useCustomerModel(\App\Models\Business::class);
    }
}
