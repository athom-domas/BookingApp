<?php

use App\Filament\Pages\BillingPage;
use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('billing page renders trial state', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDays(7)]);
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);
    $this->actingAs($user);

    Livewire::test(BillingPage::class)
        ->assertSee('Periodo di prova')
        ->assertSee('Attiva Base');
});

test('billing page renders expired state', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);
    $this->actingAs($user);

    Livewire::test(BillingPage::class)
        ->assertSee('Accesso sospeso')
        ->assertSee('Attiva Plus');
});
