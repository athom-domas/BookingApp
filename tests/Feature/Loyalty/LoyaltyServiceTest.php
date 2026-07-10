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

    $result = $this->service->redeem($this->appointment);

    expect($result['percentage'])->toBe(10)
        ->and($account->fresh()->points)->toBe(20)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100);
});

it('non riscatta se sotto soglia', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_reward_threshold' => 100]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 50]);

    expect($this->service->redeem($this->appointment))->toBe(['percentage' => 0, 'amount' => null]);
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

    $first = $this->service->redeem($this->appointment);
    expect($first['percentage'])->toBe(10)
        ->and($first['amount'])->toBeNull();

    $second = $this->service->redeem($this->appointment);
    expect($second['percentage'])->toBe(0)
        ->and($second['amount'])->toBeNull();

    expect($account->fresh()->points)->toBe(150);
});

it('impedisce a livello DB due transazioni dello stesso tipo per un appuntamento', function () {
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 0]);

    LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'appointment_id'     => $this->appointment->id,
        'type'               => 'earn',
        'points'             => 10,
    ]);

    expect(fn () => LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'appointment_id'     => $this->appointment->id,
        'type'               => 'earn',
        'points'             => 10,
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

it('accrue resta idempotente anche sotto vincolo unico (nessuna eccezione)', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 50.0);
    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(50)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('redeem ritorna 0 se loyalty è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 200]);

    expect($this->service->redeem($this->appointment))->toBe(['percentage' => 0, 'amount' => null]);
});

it('reverse è idempotente', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->service->accrue($this->appointment, 50.0);
    $this->service->reverse($this->appointment);
    $this->service->reverse($this->appointment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'reverse')->count())->toBe(1);
});

it('accredita usando il ratio corretto', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 2]);
    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(100);
});

it('isola i punti per business', function () {
    $realBusinessId = app('current_business_id');
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->service->accrue($this->appointment, 50.0);

    app()->instance('current_business_id', 999);
    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();

    app()->instance('current_business_id', $this->business->id);
    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(50);
});

it('riscatta usando un livello specifico tra multipli tier', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
        'loyalty_tiers'             => [
            ['threshold' => 100, 'percentage' => 10],
            ['threshold' => 200, 'percentage' => 20],
            ['threshold' => 500, 'percentage' => 50],
        ],
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 250]);

    $result = $this->service->redeem($this->appointment, ['threshold' => 200, 'percentage' => 20]);

    expect($result['percentage'])->toBe(20)
        ->and($result['amount'])->toBeNull()
        ->and($account->fresh()->points)->toBe(50)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-200);
});

it('redeem senza tier usa il primo tier disponibile', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
        'loyalty_tiers'             => [
            ['threshold' => 100, 'percentage' => 10],
            ['threshold' => 200, 'percentage' => 20],
        ],
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 250]);

    $result = $this->service->redeem($this->appointment);

    expect($result['percentage'])->toBe(10)
        ->and($account->fresh()->points)->toBe(150);
});

it('riscatta con livello singolo quando loyalty_tiers è vuoto (fallback)', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
        'loyalty_tiers'             => null,
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 120]);

    $result = $this->service->redeem($this->appointment);

    expect($result['percentage'])->toBe(10)
        ->and($result['amount'])->toBeNull()
        ->and($account->fresh()->points)->toBe(20);
});

it('riscatta con importo fisso in euro', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_tiers'             => [
            ['threshold' => 100, 'percentage' => null, 'amount' => 15],
        ],
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 150]);

    $result = $this->service->redeem($this->appointment);

    expect($result['percentage'])->toBe(0)
        ->and($result['amount'])->toBe(15.0)
        ->and($account->fresh()->points)->toBe(50);
});
