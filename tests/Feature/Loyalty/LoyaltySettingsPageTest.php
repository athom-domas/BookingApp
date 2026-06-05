<?php

use App\Filament\Pages\SystemSettings;
use App\Models\Business;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('salva le impostazioni fedeltà dalla pagina admin', function () {
    livewire(SystemSettings::class)
        ->set('data.loyalty_enabled', true)
        ->set('data.loyalty_points_per_euro', 2)
        ->set('data.loyalty_reward_threshold', 150)
        ->set('data.loyalty_reward_percentage', 12)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SystemSetting::isLoyaltyEnabled())->toBeTrue()
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(150);
});
