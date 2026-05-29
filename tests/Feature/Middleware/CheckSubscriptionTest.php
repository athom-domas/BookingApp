<?php

use App\Http\Middleware\CheckSubscription;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

test('allows admin access when business is on trial', function () {
    $business = Business::factory()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $request = Request::create('/admin/' . $business->subdomain . '/appointments');
    $request->setUserResolver(fn() => $user);

    $called = false;
    (new CheckSubscription())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});

test('redirects admin to billing when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $request = Request::create('/admin/' . $business->subdomain . '/appointments');
    $request->setUserResolver(fn() => $user);

    $response = (new CheckSubscription())->handle($request, fn() => new Response());

    expect($response->getStatusCode())->toBe(302);
    expect($response->headers->get('Location'))->toContain('abbonamento');
});

test('returns 403 for staff when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('staff');

    app()->instance('current_business_id', $business->id);

    $request = Request::create('/admin/' . $business->subdomain . '/appointments');
    $request->setUserResolver(fn() => $user);

    expect(fn() => (new CheckSubscription())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('billing page itself is accessible even when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $request = Request::create('/admin/' . $business->subdomain . '/abbonamento');
    $request->setRouteResolver(function () {
        $route = new \Illuminate\Routing\Route(['GET'], '/admin/{tenant}/abbonamento', []);
        $route->name('filament.admin.pages.abbonamento');
        return $route;
    });
    $request->setUserResolver(fn() => $user);

    $called = false;
    (new CheckSubscription())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});
