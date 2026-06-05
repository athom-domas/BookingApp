<?php

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LoyaltyService;

beforeEach(function () {
    $this->service = app(LoyaltyService::class);
    $this->customer = User::factory()->create();
    $this->appointment = Appointment::factory()->create(['user_id' => $this->customer->id]);
});

it('non accredita se il programma è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('accredita floor(amount * ratio) punti e crea una earn', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 49.99);

    $account = LoyaltyAccount::where('user_id', $this->customer->id)->first();
    expect($account->points)->toBe(49)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('è idempotente: non accredita due volte per lo stesso appuntamento', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 50.0);
    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(50)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('non crea transazioni se i punti sono 0', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 0.4);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('riscatta la percentuale e scala la soglia di punti', function () {
    SystemSetting::current()->update([
        'loyalty_enabled' => true,
        'loyalty_reward_threshold' => 100,
        'loyalty_reward_percentage' => 10,
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 120]);

    $percentage = $this->service->redeem($this->appointment);

    expect($percentage)->toBe(10)
        ->and($account->fresh()->points)->toBe(20)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100);
});

it('non riscatta se sotto soglia', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_reward_threshold' => 100]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 50]);

    expect($this->service->redeem($this->appointment))->toBe(0);
});

it('storna l accredito di un appuntamento', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->service->accrue($this->appointment, 50.0);

    $this->service->reverse($this->appointment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'reverse')->first()->points)->toBe(-50);
});

it('non ripristina i punti riscattati quando un appuntamento riscattato viene stornato', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 1,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 100]);

    $this->service->redeem($this->appointment);
    $this->service->accrue($this->appointment, 90.0);
    expect($account->fresh()->points)->toBe(90);

    $this->service->reverse($this->appointment);

    expect($account->fresh()->points)->toBe(0);
});

it('non riscatta due volte per lo stesso appuntamento', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 250]);

    expect($this->service->redeem($this->appointment))->toBe(10)
        ->and($this->service->redeem($this->appointment))->toBe(0)
        ->and($account->fresh()->points)->toBe(150);
});
