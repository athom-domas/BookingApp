<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withCommands([
        App\Console\Commands\SendReminderCommand::class,
        App\Console\Commands\TestWaitlistCommand::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->preventRequestForgery(except: [
            'stripe/webhook',
        ]);

        $middleware->alias([
            'tenant.user'        => \App\Http\Middleware\EnsureUserBelongsToCurrentBusiness::class,
            'tenant.status'      => \App\Http\Middleware\EnforceTenantStatus::class,
            'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\SubdomainMiddleware::class,
        ]);

        $middleware->api(append: [
            \App\Http\Middleware\SubdomainMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
