<?php

use App\Models\SalonProfile;

beforeEach(function () {
    $profile = SalonProfile::current();
    $profile->clearMediaCollection('logo');
    $profile->update([
        'phone'           => null,
        'address'         => null,
        'opening_hours'   => null,
        'instagram_url'   => null,
        'facebook_url'    => null,
        'whatsapp_number' => null,
    ]);
});

it('booking page shows salon name from profile', function () {
    SalonProfile::current()->update(['name' => 'Test Salone']);

    $this->get('/')->assertSee('Test Salone');
});

it('booking page shows fallback logo when no logo is set', function () {
    SalonProfile::current();

    $this->get('/')->assertSee('img/logo.png');
});

it('booking page shows contact footer when fields are set', function () {
    SalonProfile::current()->update([
        'phone'   => '+39 02 999999',
        'address' => 'Via Roma 1',
    ]);

    $this->get('/')
        ->assertSee('+39 02 999999')
        ->assertSee('Via Roma 1');
});

it('contact footer is hidden when all contact fields are null', function () {
    SalonProfile::current();

    $this->get('/')->assertDontSee('class="sf-contact-list"', false);
});
