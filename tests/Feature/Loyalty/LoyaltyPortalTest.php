<?php

use App\Models\LoyaltyAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');
});

it('mostra il saldo punti nel portale quando il programma è attivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_reward_threshold' => 100]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 60]);

    $this->actingAs($this->customer)
        ->get('/portale/appuntamenti')
        ->assertOk()
        ->assertSee('Programma fedeltà')
        ->assertSee('60');
});

it('nasconde la card fedeltà quando il programma è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    $this->actingAs($this->customer)
        ->get('/portale/appuntamenti')
        ->assertOk()
        ->assertDontSee('Programma fedeltà');
});

it('mostra il badge sconto disponibile quando il cliente raggiunge la soglia', function () {
    SystemSetting::current()->update([
        'loyalty_enabled' => true,
        'loyalty_reward_threshold' => 100,
        'loyalty_reward_percentage' => 10,
    ]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 150]);

    $this->actingAs($this->customer)
        ->get('/portale/appuntamenti')
        ->assertOk()
        ->assertSee('Sconto disponibile')
        ->assertSee('al prossimo appuntamento');
});
