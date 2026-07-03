<?php

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;

it('crea un account fedeltà con business_id auto e relazione utente', function () {
    $user = User::factory()->create();

    $account = LoyaltyAccount::create(['user_id' => $user->id, 'points' => 0]);

    expect($account->business_id)->toBe($this->business->id)
        ->and($account->points)->toBe(0)
        ->and($account->user->id)->toBe($user->id);
});

it('somma le transazioni nel ledger e le collega all account', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::create(['user_id' => $user->id, 'points' => 0]);

    LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'type'               => 'earn',
        'points'             => 50,
    ]);
    LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'type'               => 'redeem',
        'points'             => -20,
    ]);

    expect((int) $account->transactions()->sum('points'))->toBe(30)
        ->and($account->transactions->first()->business_id)->toBe($this->business->id);
});
