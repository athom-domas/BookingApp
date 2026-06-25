<?php

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Mockery\MockInterface;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\LoginLink;
use Stripe\StripeClient;

beforeEach(function () {
    $this->makeService = function (MockInterface $mockStripe): StripeConnectService {
        return new StripeConnectService($mockStripe);
    };
});

it('createAccount crea un StripeConnectAccount con stripe_account_id', function () {
    $fakeAccount = Account::constructFrom(['id' => 'acct_test123', 'object' => 'account']);

    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('create')->once()->andReturn($fakeAccount);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    $account = ($this->makeService)($mockStripe)->createAccount($this->business);

    expect($account->stripe_account_id)->toBe('acct_test123');
    expect($account->business_id)->toBe($this->business->id);
    expect($account->status)->toBe('pending');
});

it('createAccount non crea duplicato se già esiste un account per il business', function () {
    $existing = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_existing',
    ]);

    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('create')->never();
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    $account = ($this->makeService)($mockStripe)->createAccount($this->business);

    expect($account->id)->toBe($existing->id);
    expect(StripeConnectAccount::where('business_id', $this->business->id)->count())->toBe(1);
});

it('calculatePlatformFee usa override business se presente', function () {
    $this->business->update(['stripe_platform_fee_percent' => 3.0]);
    $mockStripe = Mockery::mock(StripeClient::class);
    $result = ($this->makeService)($mockStripe)->calculatePlatformFee($this->business, 10000);

    expect($result['cents'])->toBe(300);
    expect($result['percent'])->toBe(3.0);
});

it('calculatePlatformFee usa fee globale se business non ha override', function () {
    \App\Models\SystemSetting::current()->update(['stripe_platform_fee_percent' => 2.0]);
    $mockStripe = Mockery::mock(StripeClient::class);
    $result = ($this->makeService)($mockStripe)->calculatePlatformFee($this->business, 10000);

    expect($result['cents'])->toBe(200);
    expect($result['percent'])->toBe(2.0);
});

it('syncFromStripe aggiorna charges_enabled e status', function () {
    $connectAccount = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_sync_test',
    ]);

    $fakeAccount = Account::constructFrom([
        'id'               => 'acct_sync_test',
        'object'           => 'account',
        'charges_enabled'  => true,
        'payouts_enabled'  => true,
        'details_submitted'=> true,
        'capabilities'     => ['card_payments' => 'active', 'transfers' => 'active'],
        'requirements'     => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
        'default_currency' => 'eur',
        'country'          => 'IT',
    ]);

    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('retrieve')->with('acct_sync_test')->andReturn($fakeAccount);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    ($this->makeService)($mockStripe)->syncFromStripe($connectAccount);

    $connectAccount->refresh();
    expect($connectAccount->charges_enabled)->toBeTrue();
    expect($connectAccount->status)->toBe('active');
    expect($connectAccount->country)->toBe('IT');
});
