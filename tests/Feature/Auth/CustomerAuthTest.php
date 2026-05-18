<?php

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('registers a customer and signs them in', function () {
    $response = $this->post('/register', [
        'name' => 'Mario Rossi',
        'email' => 'mario@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $user = User::where('email', 'mario@example.com')->first();

    $response->assertRedirect(route('portal.appointments.index'));
    expect($user)->not->toBeNull();
    expect($user->hasRole('customer'))->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('logs in and logs out a customer', function () {
    $user = User::factory()->create([
        'email' => 'customer@example.com',
        'password' => 'password123',
    ]);
    $user->assignRole('customer');

    $this->post('/login', [
        'email' => 'customer@example.com',
        'password' => 'password123',
    ])->assertRedirect(route('portal.appointments.index'));

    $this->assertAuthenticatedAs($user);

    $this->post('/logout')->assertRedirect(route('booking.index'));
    $this->assertGuest();
});

it('stores intended URL from ?return param on login GET', function () {
    $this->get(route('login') . '?return=/prenota')
        ->assertOk();

    expect(session('url.intended'))->toBe('/prenota');
});

it('redirects to ?return URL after successful login', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('customer');

    $this->withSession(['url.intended' => '/prenota'])
        ->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/prenota');
});

it('stores intended URL from ?return param on register GET', function () {
    $this->get(route('register') . '?return=/prenota')
        ->assertOk();

    expect(session('url.intended'))->toBe('/prenota');
});

it('redirects to intended URL after registration when ?return was set', function () {
    $this->withSession(['url.intended' => '/prenota'])
        ->post(route('register'), [
            'name'                  => 'Test User',
            'email'                 => 'test@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->assertRedirect('/prenota');
});

it('does not store protocol-relative URL from ?return param on login GET', function () {
    $this->get(route('login') . '?return=//evil.com')
        ->assertOk();

    expect(session('url.intended'))->toBeNull();
});

it('does not store protocol-relative URL from ?return param on register GET', function () {
    $this->get(route('register') . '?return=//evil.com')
        ->assertOk();

    expect(session('url.intended'))->toBeNull();
});

it('preserves ?return across GET login and POST login redirect', function () {
    $user = User::factory()->create(['password' => bcrypt('password')]);
    $user->assignRole('customer');

    // Visit login with ?return to store url.intended in session
    $response = $this->get(route('login') . '?return=/prenota');
    $response->assertOk();

    // Now POST login — should redirect to /prenota (not the portal dashboard)
    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect('/prenota');
});
