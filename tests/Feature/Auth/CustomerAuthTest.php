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
