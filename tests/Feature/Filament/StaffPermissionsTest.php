<?php

use App\Filament\Pages\ReportPage;
use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    foreach ([
        'appointments.view_all',
        'appointments.create',
        'appointments.edit',
        'appointments.delete',
        'appointments.payments',
        'customers.view',
        'customers.create',
        'customers.edit',
        'customers.delete',
        'reports.view',
        'reports.view_revenue',
    ] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }

    $this->business = Business::withoutGlobalScopes()->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);
});

it('admin can grant permissions to staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.appointments_visibility', 'all')
        ->set('data.appointments_management', 'full')
        ->set('data.customers_management', 'none')
        ->set('data.reports_visibility', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.create'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.edit'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.payments'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.view'))->toBeFalse();
});

it('admin can revoke permissions from staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['appointments.view_all', 'reports.view']);

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.appointments_visibility', 'personal')
        ->set('data.appointments_management', 'view_only')
        ->set('data.customers_management', 'none')
        ->set('data.reports_visibility', 'none')
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeFalse();
    expect($staff->hasPermissionTo('reports.view'))->toBeFalse();
});

it('staff without appointments.create cannot create appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeFalse();
});

it('staff with appointments.create can create appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.create');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeTrue();
});

it('staff without view_all sees only own appointments in list', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $otherStaff = User::factory()->create(['business_id' => $this->business->id]);
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt])
        ->assertCanNotSeeTableRecords([$otherAppt]);
});

it('staff with view_all sees all appointments in list', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.view_all');

    $otherStaff = User::factory()->create(['business_id' => $this->business->id]);
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id, 'scheduled_date' => now()->addDays(365)]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id, 'scheduled_date' => now()->addDays(364)]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt, $otherAppt]);
});

it('seeder creates the 11 staff permissions', function () {
    $permNames = [
        'appointments.view_all', 'appointments.create', 'appointments.edit',
        'appointments.delete', 'appointments.payments',
        'customers.view', 'customers.create', 'customers.edit', 'customers.delete',
        'reports.view', 'reports.view_revenue',
    ];
    Permission::whereIn('name', $permNames)->delete();
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    (new \Database\Seeders\DatabaseSeeder)->run();

    foreach ($permNames as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())
            ->toBeTrue("Permission {$perm} missing");
    }
});

it('staff without customers.view cannot access customer list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(CustomerResource::canViewAny())->toBeFalse();
});

it('staff with customers.view can access customer list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('customers.view');
    $this->actingAs($staff);

    expect(CustomerResource::canViewAny())->toBeTrue();
});

it('staff without appointments.payments cannot access payment list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(PaymentResource::canViewAny())->toBeFalse();
});

it('staff with appointments.payments can access payment list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.payments');
    $this->actingAs($staff);

    expect(PaymentResource::canViewAny())->toBeTrue();
});

it('staff without reports.view cannot access report page', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(ReportPage::canAccess())->toBeFalse();
});

it('staff with reports.view can access report page', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('reports.view');
    $this->actingAs($staff);

    expect(ReportPage::canAccess())->toBeTrue();
});

it('staff without appointments.edit cannot edit appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $appt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canEdit($appt))->toBeFalse();
});

it('staff with appointments.edit can edit own appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.edit');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $appt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id, 'status' => 'pending']);

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canEdit($appt))->toBeTrue();
});

it('staff without appointments.delete cannot delete appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $appt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canDelete($appt))->toBeFalse();
});

it('staff with appointments.delete can delete appointments', function () {
    app()->instance('current_business_id', $this->business->id);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.delete');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $appt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canDelete($appt))->toBeTrue();
});

it('staff with reports.view but not view_revenue does not see revenue widgets', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('reports.view');
    $this->actingAs($staff);

    $page = new ReportPage();
    $widgets = $page->getWidgets();

    expect($widgets)->toContain(\App\Filament\Widgets\Reports\InsightStatsWidget::class)
        ->and($widgets)->not->toContain(\App\Filament\Widgets\Reports\RevenueStatsWidget::class)
        ->and($widgets)->not->toContain(\App\Filament\Widgets\Reports\RevenueChartWidget::class)
        ->and($widgets)->not->toContain(\App\Filament\Widgets\Reports\StaffPerformanceWidget::class);
});

it('staff with reports.view_revenue sees all widgets', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['reports.view', 'reports.view_revenue']);
    $this->actingAs($staff);

    $page = new ReportPage();
    $widgets = $page->getWidgets();

    expect($widgets)->toContain(\App\Filament\Widgets\Reports\RevenueStatsWidget::class)
        ->and($widgets)->toContain(\App\Filament\Widgets\Reports\RevenueChartWidget::class)
        ->and($widgets)->toContain(\App\Filament\Widgets\Reports\StaffPerformanceWidget::class);
});

it('staff without customers.create cannot create customers', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('customers.view');
    $this->actingAs($staff);

    expect(\App\Filament\Resources\CustomerResource::canCreate())->toBeFalse();
});

it('staff with customers.create can create customers', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['customers.view', 'customers.create']);
    $this->actingAs($staff);

    expect(\App\Filament\Resources\CustomerResource::canCreate())->toBeTrue();
});
