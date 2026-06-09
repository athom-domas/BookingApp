<?php

use App\Models\Business;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    app()->instance('current_business_id', 1);
});

function mockSocialiteUser(string $id, string $email, string $name): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->id = $id;
    $socialiteUser->email = $email;
    $socialiteUser->name = $name;
    $socialiteUser->shouldReceive('getId')->andReturn($id);
    $socialiteUser->shouldReceive('getEmail')->andReturn($email);
    $socialiteUser->shouldReceive('getName')->andReturn($name);

    $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redirige a Google OAuth', function () {
    $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google'))->assertRedirect();
});

it('crea un nuovo utente customer via Google e fa login', function () {
    $business = Business::find(1);
    app()->instance('current_business_id', $business->id);

    mockSocialiteUser('google-123', 'nuovo@example.com', 'Nuovo Utente');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $user = User::where('email', 'nuovo@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-123');
    expect($user->hasRole('customer'))->toBeTrue();
    expect($user->business_id)->toBe($business->id);
    expect($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('collega google_id a utente esistente con stessa email e fa login', function () {
    $business = Business::find(1);
    app()->instance('current_business_id', $business->id);

    $user = User::factory()->create([
        'email' => 'esistente@example.com',
        'google_id' => null,
        'business_id' => $business->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-456', 'esistente@example.com', 'Utente Esistente');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $user->refresh();
    expect($user->google_id)->toBe('google-456');
    $this->assertAuthenticatedAs($user);
});

it('fa login diretto se google_id già registrato', function () {
    $business = Business::find(1);
    app()->instance('current_business_id', $business->id);

    $user = User::factory()->create([
        'email' => 'gia@example.com',
        'google_id' => 'google-789',
        'business_id' => $business->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-789', 'gia@example.com', 'Già Registrato');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $this->assertAuthenticatedAs($user);
});

it('rifiuta utente Google che appartiene a un altro business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    app()->instance('current_business_id', $businessB->id);

    $user = User::factory()->create([
        'email' => 'altrobusiness@example.com',
        'business_id' => $businessA->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-999', 'altrobusiness@example.com', 'Altro Business');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
