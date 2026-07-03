<?php

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ServiceCategoryResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\StaffResource;
use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('appointment list page renders', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(AppointmentResource::getUrl('index'))
        ->assertSuccessful();
});

it('service list page renders', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(ServiceResource::getUrl('index'))
        ->assertSuccessful();
});

it('payment list page renders', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(PaymentResource::getUrl('index'))
        ->assertSuccessful();
});

it('staff list page renders for admins', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(StaffResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Staff');
});

it('staff resource is forbidden for staff users', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->businesses()->attach($this->business->id);

    $this->actingAs($staff)
        ->get(StaffResource::getUrl('index'))
        ->assertForbidden();
});

it('customer list page renders for admins', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $this->actingAs($admin)
        ->get(CustomerResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee('Clienti')
        ->assertSee($customer->email);
});

it('customer resource is forbidden for staff users', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->businesses()->attach($this->business->id);

    $this->actingAs($staff)
        ->get(CustomerResource::getUrl('index'))
        ->assertForbidden();
});

it('manage availability page renders for a staff member', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($admin)
        ->get(StaffResource::getUrl('manage-availability', ['record' => $staff]))
        ->assertSuccessful();
});

it('service category list page renders', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->businesses()->attach($this->business->id);

    $this->actingAs($admin)
        ->get(ServiceCategoryResource::getUrl('index'))
        ->assertSuccessful();
});
