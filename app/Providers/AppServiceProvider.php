<?php

namespace App\Providers;

use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class, function () {
            return new PaymentService(new StripeClient(config('services.stripe.secret')));
        });

        $this->app->singleton(\App\Services\NotificationService::class, function () {
            $client = new \Twilio\Rest\Client(
                config('services.twilio.sid'),
                config('services.twilio.token'),
            );
            return new \App\Services\NotificationService($client->messages);
        });

        $this->app->singleton(\App\Services\GoogleCalendarService::class, function () {
            $client = new \Google\Client();
            $credPath = config('services.google.credentials');
            if (file_exists($credPath)) {
                $client->setAuthConfig($credPath);
            }
            $client->addScope(\Google\Service\Calendar::CALENDAR);
            return new \App\Services\GoogleCalendarService(
                new \Google\Service\Calendar($client)
            );
        });
    }

    public function boot(): void {}
}
