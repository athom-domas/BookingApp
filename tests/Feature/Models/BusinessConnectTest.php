<?php

use App\Models\Business;
use App\Models\StripeConnectAccount;

it('canAcceptOnlinePayments restituisce false se non esiste connected account', function () {
    expect($this->business->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce false se account pending', function () {
    StripeConnectAccount::factory()->pending()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce false se restricted', function () {
    StripeConnectAccount::factory()->restricted()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce true se account active e charges_enabled', function () {
    StripeConnectAccount::factory()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeTrue();
});

it('ha una relazione stripeConnectAccount', function () {
    $account = StripeConnectAccount::factory()->create(['business_id' => $this->business->id]);
    expect($this->business->stripeConnectAccount->id)->toBe($account->id);
});
