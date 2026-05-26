<?php

use App\Models\SystemSetting;

it('returns default waitlist offer timeout of 180 minutes', function () {
    expect(SystemSetting::getWaitlistOfferTimeout())->toBe(180);
});

it('returns custom timeout after update', function () {
    SystemSetting::current()->update(['waitlist_offer_timeout_minutes' => 120]);

    expect(SystemSetting::getWaitlistOfferTimeout())->toBe(120);
});
