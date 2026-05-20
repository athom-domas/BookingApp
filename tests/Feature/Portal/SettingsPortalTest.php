<?php

use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('redirects guests away from settings', function () {
    $this->get('/portal/settings')->assertRedirect('/login');
});

it('shows the settings page for authenticated users', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->get('/portal/settings')
        ->assertOk()
        ->assertSee('Profilo')
        ->assertSee('Notifiche');
});

// --- Profile ---

it('updates name and email', function () {
    $customer = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'New Name',
            'email' => 'new@example.com',
        ])
        ->assertRedirect();

    expect($customer->fresh())
        ->name->toBe('New Name')
        ->email->toBe('new@example.com');
});

it('requires name and email', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [])
        ->assertSessionHasErrors(['name', 'email']);
});

it('rejects duplicate email from another user', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => 'taken@example.com',
        ])
        ->assertSessionHasErrors(['email']);
});

it('allows keeping own email', function () {
    $customer = User::factory()->create(['email' => 'mine@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => 'mine@example.com',
        ])
        ->assertSessionDoesntHaveErrors();
});

it('changes password when current password is correct', function () {
    $customer = User::factory()->create(['password' => Hash::make('oldpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'oldpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionDoesntHaveErrors();

    expect(Hash::check('newpassword1', $customer->fresh()->password))->toBeTrue();
});

it('rejects wrong current password', function () {
    $customer = User::factory()->create(['password' => Hash::make('correctpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'wrongpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('requires current_password when setting new_password', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'newpassword1',
        ])
        ->assertSessionHasErrors(['current_password']);
});

it('rejects mismatched password confirmation', function () {
    $customer = User::factory()->create(['password' => Hash::make('oldpassword')]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'                      => $customer->name,
            'email'                     => $customer->email,
            'current_password'          => 'oldpassword',
            'new_password'              => 'newpassword1',
            'new_password_confirmation' => 'different',
        ])
        ->assertSessionHasErrors(['new_password']);
});

it('flashes profile_updated on success', function () {
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => 'Test',
            'email' => $customer->email,
        ])
        ->assertSessionHas('profile_updated');
});

it('nulls email_verified_at when email changes', function () {
    $customer = User::factory()->create([
        'email'              => 'old@example.com',
        'email_verified_at'  => now(),
    ]);
    $customer->assignRole('customer');

    $this->actingAs($customer)
        ->patch('/portal/settings/profile', [
            'name'  => $customer->name,
            'email' => 'new@example.com',
        ]);

    expect($customer->fresh()->email_verified_at)->toBeNull();
});
