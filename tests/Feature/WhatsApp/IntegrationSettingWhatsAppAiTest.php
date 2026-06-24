<?php

use App\Models\Business;
use App\Models\IntegrationSetting;

beforeEach(function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

it('returns defaults when whatsapp_ai fields are null', function () {
    $s = IntegrationSetting::current();
    expect($s->hasWhatsAppAiEnabled())->toBeFalse();
    expect($s->isWhatsAppBookingEnabled())->toBeTrue();
    expect($s->isWhatsAppCancellationEnabled())->toBeFalse();
    expect($s->getWhatsAppAiLanguage())->toBe('it');
    expect($s->getWhatsAppAiTimezone())->toBe('Europe/Rome');
    expect($s->getWhatsAppAiMaxTurns())->toBe(12);
});

it('finds integration setting by phone_number_id', function () {
    $setting = IntegrationSetting::current();
    $setting->update(['meta_whatsapp_phone_id' => '123456789']);

    $found = IntegrationSetting::findByPhoneNumberId('123456789');
    expect($found?->id)->toBe($setting->id);
});

it('returns null for unknown phone_number_id', function () {
    expect(IntegrationSetting::findByPhoneNumberId('unknown'))->toBeNull();
});
