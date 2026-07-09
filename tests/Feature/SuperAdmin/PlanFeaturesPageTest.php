<?php

use App\Models\PlanFeature;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

function makeSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('super_admin');
    return $user;
}

it('super_admin can see the plan features page', function () {
    $this->actingAs(makeSuperAdmin());

    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->assertSuccessful();
});

it('page shows all six feature labels', function () {
    $this->actingAs(makeSuperAdmin());

    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->assertSee('Assistente AI WhatsApp')
        ->assertSee('Google Calendar')
        ->assertSee('Lista d\'attesa');
});

it('non-superadmin cannot access', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');
    $this->actingAs($user);

    $this->get('/superadmin/piani-feature')->assertForbidden();
});

it('updating min_plan via action updates DB and flushes cache', function () {
    $this->actingAs(makeSuperAdmin());

    Cache::put('plan_feature_waitlist', 'base', 60);
    $feature = PlanFeature::where('key', 'waitlist')->first();

    // callTableAction(data:) silently drops form data in Filament 4.11.2 — use mount+set+call
    livewire(\App\Filament\SuperAdmin\Pages\PlanFeaturesPage::class)
        ->mountTableAction('edit_min_plan', $feature)
        ->set('mountedActions.0.data.min_plan', 'plus')
        ->callMountedTableAction()
        ->assertSuccessful();

    expect(PlanFeature::where('key', 'waitlist')->value('min_plan'))->toBe('plus');
    expect(Cache::has('plan_feature_waitlist'))->toBeFalse(); // flushed by model booted()
});
