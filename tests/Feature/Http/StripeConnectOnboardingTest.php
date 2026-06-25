<?php

use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Mockery\MockInterface;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = \App\Models\User::factory()->create(['business_id' => $this->business->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('redirect a Stripe per avviare onboarding', function () {
    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $account = StripeConnectAccount::factory()->pending()->make(['business_id' => $this->business->id]);
        $mock->shouldReceive('createAccount')->once()->andReturn($account);
        $mock->shouldReceive('createAccountLink')->once()->andReturn('https://connect.stripe.com/onboarding/test');
    });

    $response = $this->withoutMiddleware()->get(route('stripe.connect.start'));

    $response->assertRedirect('https://connect.stripe.com/onboarding/test');
});

it('callback aggiorna details_submitted', function () {
    $account = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_test123',
    ]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->once();
    });

    $response = $this->withoutMiddleware()->get(route('stripe.connect.callback'));

    $response->assertRedirect();
});

it('redirect non autenticato a login', function () {
    auth()->logout();
    $this->get(route('stripe.connect.start'))->assertRedirect('/login');
});
