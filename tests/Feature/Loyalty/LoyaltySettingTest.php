<?php

use App\Models\SystemSetting;

it('ha defaults fedeltà coerenti', function () {
    expect(SystemSetting::isLoyaltyEnabled())->toBeFalse()
        ->and(SystemSetting::getLoyaltyPointsPerEuro())->toBe(1)
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(100)
        ->and(SystemSetting::getLoyaltyRewardPercentage())->toBe(10);
});

it('legge i valori fedeltà salvati', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 2,
        'loyalty_reward_threshold'  => 200,
        'loyalty_reward_percentage' => 15,
    ]);

    expect(SystemSetting::isLoyaltyEnabled())->toBeTrue()
        ->and(SystemSetting::getLoyaltyPointsPerEuro())->toBe(2)
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(200)
        ->and(SystemSetting::getLoyaltyRewardPercentage())->toBe(15);
});
