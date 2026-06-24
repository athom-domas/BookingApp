<?php
use App\Models\Business;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->business = Business::factory()->create();
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('renders the WhatsApp AI section in integration settings', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
         ->get("/admin/{$this->business->subdomain}/integration-settings")
         ->assertOk()
         ->assertSee('Assistente WhatsApp');
});
