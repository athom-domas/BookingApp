<?php

use App\Models\ActivityLog;
use App\Models\Business;

it('logs exception with business_id when tenant context is bound', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    report(new RuntimeException('Tenant error test'));

    $log = ActivityLog::where('type', 'error')->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBe($business->id)
        ->and($log->level)->toBe('error')
        ->and($log->source)->toBe('exception_reporter')
        ->and($log->description)->toBe('Tenant error test');
});

it('logs exception with null business_id when no tenant context', function () {
    if (app()->bound('current_business_id')) {
        app()->forgetInstance('current_business_id');
    }

    report(new RuntimeException('Platform error test'));

    $log = ActivityLog::where('type', 'error')
        ->where('description', 'Platform error test')
        ->latest()
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->business_id)->toBeNull();
});

it('does not log 404 HTTP exceptions', function () {
    $before = ActivityLog::where('type', 'error')->count();

    report(new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException('Not found'));

    expect(ActivityLog::where('type', 'error')->count())->toBe($before);
});

it('logs HTTP 500 exceptions', function () {
    $before = ActivityLog::where('type', 'error')->count();

    report(new \Symfony\Component\HttpKernel\Exception\HttpException(500, 'Server error'));

    expect(ActivityLog::where('type', 'error')->count())->toBeGreaterThan($before);
});

it('stores exception class in properties', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);

    report(new RuntimeException('Props test'));

    $log = ActivityLog::where('description', 'Props test')->latest()->first();

    expect($log->properties)->toHaveKey('exception')
        ->and($log->properties['exception'])->toBe('RuntimeException');
});

it('does not store sensitive data in properties', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    request()->merge([
        'email' => 'customer@example.test',
        'password' => 'secret',
        '_token' => 'csrf-token',
        'nested' => ['authorization' => 'Bearer secret'],
    ]);

    report(new RuntimeException('Sensitive test'));

    $log = ActivityLog::where('description', 'Sensitive test')->latest()->first();

    $props = json_encode($log->properties ?? []);
    expect($props)
        ->toContain('customer@example.test')
        ->not->toContain('password')
        ->not->toContain('secret')
        ->not->toContain('_token')
        ->not->toContain('authorization');
});
