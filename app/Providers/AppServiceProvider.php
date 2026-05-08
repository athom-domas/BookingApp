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
    }

    public function boot(): void {}
}
