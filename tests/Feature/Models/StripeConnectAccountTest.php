<?php

use App\Models\Business;
use App\Models\StripeConnectAccount;

it('ha una relazione business', function () {
    $account = StripeConnectAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);
    expect($account->business->id)->toBe($this->business->id);
});

it('isActive restituisce true solo se status active e charges_enabled true', function () {
    $active = StripeConnectAccount::factory()->create([
        'business_id'       => $this->business->id,
        'status'            => 'active',
        'charges_enabled'   => true,
        'stripe_account_id' => 'acct_test',
    ]);
    $restricted = StripeConnectAccount::factory()->restricted()->create([
        'business_id' => Business::factory()->create()->id,
    ]);

    expect($active->isActive())->toBeTrue();
    expect($restricted->isActive())->toBeFalse();
});

it('pending factory ha charges_enabled false', function () {
    $account = StripeConnectAccount::factory()->pending()->make();
    expect($account->charges_enabled)->toBeFalse();
    expect($account->stripe_account_id)->toBeNull();
});
