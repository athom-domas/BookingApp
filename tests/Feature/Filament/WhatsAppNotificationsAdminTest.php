<?php

use App\Filament\SuperAdmin\Resources\BusinessResource\Pages\ListBusinesses;
use App\Models\Business;
use App\Models\IntegrationSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin',       'guard_name' => 'web']);
    Filament::setCurrentPanel(Filament::getPanel('superadmin'));
});

it('superadmin can enable whatsapp notifications with monthly limit', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $business = Business::factory()->create();

    Livewire::test(ListBusinesses::class)
        ->mountTableAction('whatsappNotifications', $business)
        ->set('mountedActions.0.data.whatsapp_notifications_enabled', true)
        ->set('mountedActions.0.data.whatsapp_monthly_limit', 300)
        ->callMountedTableAction()
        ->assertHasNoTableActionErrors();

    $setting = IntegrationSetting::withoutGlobalScope('business')
        ->where('business_id', $business->id)
        ->first();

    expect($setting->whatsapp_notifications_enabled)->toBeTrue();
    expect($setting->whatsapp_monthly_limit)->toBe(300);
});

it('tenant admin sees notification status in integration settings', function () {
    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
    IntegrationSetting::current()->update([
        'whatsapp_notifications_enabled' => true,
        'whatsapp_monthly_limit'         => 300,
        'whatsapp_monthly_sent'          => 12,
    ]);

    $admin = User::factory()->create(['business_id' => $business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($business->id);

    $this->actingAs($admin)
        ->get("/admin/{$business->subdomain}/integration-settings")
        ->assertOk()
        ->assertSee('Notifiche WhatsApp')
        ->assertSee('12 / 300');
});

afterEach(fn () => app()->forgetInstance('current_business_id'));
