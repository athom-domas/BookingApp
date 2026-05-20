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
