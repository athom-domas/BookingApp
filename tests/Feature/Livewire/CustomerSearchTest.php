<?php

use App\Livewire\CustomerSearch;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

test('returns empty results when query is shorter than 2 characters', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'M')
        ->assertDontSee('Mario Rossi');
});

test('returns customers matching by name', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Mario')
        ->assertSee('Mario Rossi');
});

test('returns customers matching by email', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.com']);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'mario@')
        ->assertSee('Mario Rossi');
});

test('does not return staff or admin users in results', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create(['name' => 'Mario Staff']);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Mario')
        ->assertDontSee('Mario Staff');
});

test('non-admin and non-staff users see no results', function () {
    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $customer->assignRole('customer');

    $other = User::factory()->create(['name' => 'Luigi Verdi']);
    $other->assignRole('customer');

    $this->actingAs($customer);

    Livewire::test(CustomerSearch::class)
        ->set('query', 'Luigi')
        ->assertDontSee('Luigi Verdi');
});

test('limits results to 5 customers', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    User::factory()->count(6)
        ->sequence(fn($seq) => ['name' => 'TestUser' . $seq->index])
        ->create()
        ->each(fn($c) => $c->assignRole('customer'));

    $this->actingAs($admin);

    $count = Livewire::test(CustomerSearch::class)
        ->set('query', 'TestUser')
        ->instance()
        ->results
        ->count();

    expect($count)->toBe(5);
});
