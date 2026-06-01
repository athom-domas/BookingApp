<?php

use App\Enums\BusinessStatus;
use App\Http\Middleware\EnsureUserBelongsToCurrentBusiness;
use App\Http\Middleware\SubdomainMiddleware;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    config(['app.base_domain' => 'tuogestionale.it']);
});

it('SubdomainMiddleware binds current_business_id for active subdomain', function () {
    $business = Business::factory()->create(['subdomain' => 'test-salon']);

    $request = Request::create('http://test-salon.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'test-salon.tuogestionale.it');

    $called = false;
    (new SubdomainMiddleware())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
    expect(app('current_business_id'))->toBe($business->id);
});

it('SubdomainMiddleware returns 404 for unknown subdomain', function () {
    $request = Request::create('http://unknown.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'unknown.tuogestionale.it');

    expect(fn() => (new SubdomainMiddleware())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('SubdomainMiddleware returns 503 for suspended business', function () {
    Business::factory()->suspended()->create(['subdomain' => 'suspended-salon']);

    $request = Request::create('http://suspended-salon.tuogestionale.it/prenota');
    $request->headers->set('HOST', 'suspended-salon.tuogestionale.it');

    expect(fn() => (new SubdomainMiddleware())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('SubdomainMiddleware skips when APP_BASE_DOMAIN is empty', function () {
    config(['app.base_domain' => '']);
    Business::factory()->create();

    $request = Request::create('http://localhost/prenota');
    $request->headers->set('HOST', 'localhost');

    $called = false;
    (new SubdomainMiddleware())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
    expect(app()->bound('current_business_id'))->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness allows user with matching business', function () {
    $business = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $business->id]);
    app()->instance('current_business_id', $business->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn() => $user);

    $called = false;
    (new EnsureUserBelongsToCurrentBusiness())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness blocks user from wrong business', function () {
    $b1 = Business::factory()->create();
    $b2 = Business::factory()->create();
    $user = User::factory()->create(['business_id' => $b1->id]);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn() => $user);

    expect(fn() => (new EnsureUserBelongsToCurrentBusiness())->handle($request, fn() => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('EnsureUserBelongsToCurrentBusiness allows admin linked to business via pivot', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b2->id);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn () => $admin);

    $called = false;
    (new EnsureUserBelongsToCurrentBusiness())->handle($request, function () use (&$called) {
        $called = true;
        return new Response();
    });

    expect($called)->toBeTrue();
});

it('EnsureUserBelongsToCurrentBusiness blocks admin not linked via pivot', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $b1    = Business::factory()->create();
    $b2    = Business::factory()->create();
    $admin = User::factory()->create(['business_id' => $b1->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($b1->id);
    app()->instance('current_business_id', $b2->id);

    $request = Request::create('/portal');
    $request->setUserResolver(fn () => $admin);

    expect(fn () => (new EnsureUserBelongsToCurrentBusiness())->handle($request, fn () => new Response()))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
