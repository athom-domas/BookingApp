<?php

use App\Filament\Resources\AdminResource;
use App\Filament\Resources\AdminResource\Pages\EditAdmin;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Service;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('admin list shows only admin users', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['email' => 'staff@test.com', 'business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $customer = User::factory()->create(['email' => 'customer@test.com', 'business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $this->actingAs($admin);

    $this->get(AdminResource::getUrl('index'))
        ->assertSuccessful()
        ->assertSee($admin->email)
        ->assertDontSee('staff@test.com')
        ->assertDontSee('customer@test.com');
});

it('non-admin cannot access admin resource', function () {
    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($staff);

    $this->get(AdminResource::getUrl('index'))
        ->assertForbidden();
});

it('toggle ON assigns staff role to admin', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $service = Service::factory()->create(['active' => true]);

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', true)
        ->set('data.services', [$service->id])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->hasRole('staff'))->toBeTrue();
    expect($admin->services()->whereKey($service->id)->exists())->toBeTrue();
});

it('toggle OFF removes staff role from admin', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', false)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($admin->refresh()->hasRole('staff'))->toBeFalse();
});

it('toggle OFF with future confirmed appointments does not block the operation', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    Appointment::factory()->create([
        'staff_id'       => $admin->id,
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDay(),
    ]);

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->set('data.works_as_staff', false)
        ->call('save')
        ->assertHasNoFormErrors();

    // Operation is not blocked — role is removed
    expect($admin->refresh()->hasRole('staff'))->toBeFalse();
});

it('admin with staff role appears in staff resource query', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $inQuery = User::whereHas('roles', fn(Builder $query) =>
        $query->where('name', 'staff')->where('guard_name', 'web')
    )->whereKey($admin->id)->exists();

    expect($inQuery)->toBeTrue();
});

it('edit form populates works_as_staff based on current role', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');
    $admin->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditAdmin::class, ['record' => $admin->id])
        ->assertSet('data.works_as_staff', true);
});
