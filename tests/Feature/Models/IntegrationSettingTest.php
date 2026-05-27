<?php

use App\Models\IntegrationSetting;

describe('IntegrationSetting', function () {
    test('current() creates record on first call', function () {
        IntegrationSetting::query()->delete();

        expect(IntegrationSetting::count())->toBe(0);

        IntegrationSetting::current();

        expect(IntegrationSetting::count())->toBe(1);
    });

    test('current() returns same record on subsequent calls', function () {
        IntegrationSetting::query()->delete();

        $first = IntegrationSetting::current();
        $second = IntegrationSetting::current();

        expect($first->id)->toBe($second->id);
    });

    test('getters return null when not set', function () {
        IntegrationSetting::query()->delete();
        IntegrationSetting::current();

        expect(IntegrationSetting::getStripePublicKey())->toBeNull();
        expect(IntegrationSetting::getStripeSecretKey())->toBeNull();
        expect(IntegrationSetting::getStripeWebhookSecret())->toBeNull();
        expect(IntegrationSetting::getTwilioSid())->toBeNull();
        expect(IntegrationSetting::getTwilioToken())->toBeNull();
        expect(IntegrationSetting::getTwilioFrom())->toBeNull();
        expect(IntegrationSetting::getGoogleCalendarId())->toBeNull();
        expect(IntegrationSetting::getGoogleCredentialsJson())->toBeNull();
    });

    test('getters return decrypted values after saving encrypted field', function () {
        IntegrationSetting::query()->delete();

        $setting = IntegrationSetting::current();
        $setting->update(['stripe_secret_key' => 'sk_test_secret']);

        expect(IntegrationSetting::getStripeSecretKey())->toBe('sk_test_secret');
    });
});
