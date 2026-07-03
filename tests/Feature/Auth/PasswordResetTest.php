<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('shows the forgot password form', function () {
    $this->get('/password/forgot')->assertOk();
});

it('sends a reset link to a registered email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/password/forgot', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPasswordNotification::class);
});

it('shows the new password form with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/password/reset/{$token}?email={$user->email}")->assertOk();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->post('/password/reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

it('rejects mismatched passwords on reset', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');
});

it('resets the password successfully and redirects to login', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});
