<?php

use App\Models\SalonProfile;

it('booking page shows salon name from profile', function () {
    SalonProfile::current()->update(['name' => 'Test Salone', 'primary_color' => '#abcdef']);

    $this->get('/')->assertSee('Test Salone')->assertSee('#abcdef');
});

it('booking page shows fallback logo when no logo is set', function () {
    SalonProfile::current();

    $this->get('/')->assertSee('img/logo.png');
});

it('booking page shows contact footer when fields are set', function () {
    SalonProfile::current()->update([
        'phone'   => '+39 02 999999',
        'address' => 'Via Roma 1',
        'website' => 'https://salone.it',
    ]);

    $this->get('/')
        ->assertSee('+39 02 999999')
        ->assertSee('Via Roma 1')
        ->assertSee('https://salone.it');
});

it('contact footer is hidden when all contact fields are null', function () {
    SalonProfile::current();

    $this->get('/')->assertDontSee('<footer', false);
});
