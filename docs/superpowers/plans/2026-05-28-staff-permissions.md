# Staff Permissions Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow admins to toggle 5 granular Spatie permissions per staff member, controlling what they can see and do in the Filament admin panel.

**Architecture:** Use Spatie Laravel Permission's named permissions (already installed, tables already migrated). Seed 5 permissions once in DatabaseSeeder. Admin assigns/revokes them per staff user via a CheckboxList in StaffResource edit form. Authorization checks in each Filament resource/page are updated to accept the corresponding permission in addition to the admin role.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Spatie Permission (already installed)

---

## File Map

| File | Change |
|---|---|
| `database/seeders/DatabaseSeeder.php` | Seed 5 named permissions |
| `app/Filament/Resources/StaffResource.php` | Add CheckboxList in form |
| `app/Filament/Resources/StaffResource/Pages/EditStaff.php` | Override `afterSave()` to sync permissions |
| `app/Filament/Resources/AppointmentResource.php` | Update `canCreate()`, `modifyQueryUsing`, staff filter visibility |
| `app/Filament/Widgets/AppointmentCalendarWidget.php` | Update `fetchEvents()`, `authorizeAppointmentAccess()` |
| `app/Filament/Pages/AppointmentCalendar.php` | Show staff filter for staff with `view_all` |
| `app/Filament/Resources/CustomerResource.php` | Update `canViewAny()`, `canView()`, `canEdit()` |
| `app/Filament/Resources/PaymentResource.php` | Update `canViewAny()` |
| `app/Filament/Pages/ReportPage.php` | Update `canAccess()` |

---

## Task 1: Seed the 5 Spatie permissions

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Filament/StaffPermissionsTest.php` (new file)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
<?php

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
});

it('seeder creates the 5 staff permissions', function () {
    app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    (new \Database\Seeders\DatabaseSeeder)->run();

    foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $perm) {
        expect(Permission::where('name', $perm)->where('guard_name', 'web')->exists())->toBeTrue("Permission {$perm} missing");
    }
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "seeder creates"
```

Expected: FAIL — permissions don't exist yet.

- [ ] **Step 3: Add permission seeding to DatabaseSeeder**

In `database/seeders/DatabaseSeeder.php`, add `use Spatie\Permission\Models\Permission;` to the imports, then after the roles loop (after line 21), insert:

```php
        foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
```

The full `run()` method beginning should look like:

```php
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['admin', 'staff', 'customer', 'super_admin'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        foreach (['appointments.view_all', 'appointments.create', 'customers.view', 'payments.manage', 'reports.view'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        // ... rest unchanged
```

- [ ] **Step 4: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "seeder creates"
```

Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add database/seeders/DatabaseSeeder.php tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: seed 5 staff permissions"
```

---

## Task 2: StaffResource — permissions CheckboxList

**Files:**
- Modify: `app/Filament/Resources/StaffResource.php`
- Modify: `app/Filament/Resources/StaffResource/Pages/EditStaff.php`
- Test: `tests/Feature/Filament/StaffPermissionsTest.php` (add tests)

Context: `EditStaff.php` already overrides `handleRecordUpdate()` to preserve the staff role. Add `afterSave()` to sync permissions after the record is saved. The CheckboxList in the form uses `->dehydrated(false)` so Filament doesn't try to set it on the User model directly; instead, `afterSave()` reads the raw form state and calls `syncPermissions()`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
use App\Models\User;
use App\Filament\Resources\StaffResource\Pages\EditStaff;
use Livewire\Livewire;

it('admin can grant permissions to staff via edit form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.staff_permissions', ['appointments.view_all', 'appointments.create'])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.create'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.view'))->toBeFalse();
});

it('admin can revoke permissions from staff via edit form', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo(['appointments.view_all', 'reports.view']);

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.staff_permissions', [])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeFalse();
    expect($staff->hasPermissionTo('reports.view'))->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "grant permissions|revoke permissions"
```

Expected: FAIL.

- [ ] **Step 3: Add CheckboxList to StaffResource form**

In `app/Filament/Resources/StaffResource.php`, add `use Filament\Forms\Components\CheckboxList;` to the imports. Then at the end of the `form()` schema array (after the `Toggle::make('receive_email_notifications')` entry), add:

```php
            CheckboxList::make('staff_permissions')
                ->label('Permessi pannello admin')
                ->options([
                    'appointments.view_all' => 'Vedi tutti gli appuntamenti',
                    'appointments.create'   => 'Crea appuntamenti',
                    'customers.view'        => 'Gestisci clienti',
                    'payments.manage'       => 'Registra pagamenti',
                    'reports.view'          => 'Vedi report',
                ])
                ->afterStateHydrated(fn ($component, $record) =>
                    $component->state($record ? $record->getPermissionNames()->toArray() : [])
                )
                ->dehydrated(false)
                ->visibleOn('edit')
                ->columnSpanFull(),
```

- [ ] **Step 4: Add afterSave() to EditStaff**

In `app/Filament/Resources/StaffResource/Pages/EditStaff.php`, add this method after `handleRecordUpdate()`:

```php
    protected function afterSave(): void
    {
        $permissions = $this->form->getRawState()['staff_permissions'] ?? [];
        $this->record->syncPermissions($permissions);
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "grant permissions|revoke permissions"
```

Expected: PASS.

- [ ] **Step 6: Run full StaffResource test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffResourceTest.php
```

Expected: all pass.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/StaffResource.php app/Filament/Resources/StaffResource/Pages/EditStaff.php tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: add permissions CheckboxList to staff edit form"
```

---

## Task 3: AppointmentResource authorization

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource.php`
- Test: `tests/Feature/Filament/StaffPermissionsTest.php` (add tests)

Context: Three changes in `AppointmentResource`:
1. `canCreate()` currently returns `! isStaff()` — change to allow staff with `appointments.create`
2. In `table()`, `$isStaff` drives `modifyQueryUsing` and filter visibility — update to account for `appointments.view_all`
3. The staff filter `->hidden($isStaff)` should show for staff with `view_all`

The `canEdit()` and `canDelete()` are NOT changed — staff can still only edit their own non-completed/cancelled appointments.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
use App\Filament\Resources\AppointmentResource\Pages\ListAppointments;
use App\Filament\Resources\AppointmentResource\Pages\CreateAppointment;
use App\Models\Appointment;

it('staff without appointments.create cannot access create page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeFalse();
});

it('staff with appointments.create can access create page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.create');

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeTrue();
});

it('staff without view_all sees only own appointments in list', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt])
        ->assertCanNotSeeTableRecords([$otherAppt]);
});

it('staff with view_all sees all appointments in list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.view_all');

    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $ownAppt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    Livewire::test(ListAppointments::class)
        ->assertCanSeeTableRecords([$ownAppt, $otherAppt]);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "appointments.create|view_all|own appointments|all appointments"
```

Expected: FAIL.

- [ ] **Step 3: Update AppointmentResource**

In `app/Filament/Resources/AppointmentResource.php`, replace `canCreate()`:

```php
    public static function canCreate(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.create')) ?? false;
    }
```

In the `table()` method, replace the top of the method where `$isStaff` is computed and used:

```php
    public static function table(Table $table): Table
    {
        $user        = auth()->user();
        $isStaff     = $user?->isStaff() ?? false;
        $hasViewAll  = $isStaff && ($user?->can('appointments.view_all') ?? false);

        return $table
            ->modifyQueryUsing(
                fn(Builder $query) =>
                ($isStaff && ! $hasViewAll) ? $query->where('staff_id', $user->id) : $query
            )
```

Also update the staff filter hidden condition (find `->hidden($isStaff)` on the `SelectFilter::make('staff')` and replace):

```php
                SelectFilter::make('staff')
                    ->label('Staff')
                    ->relationship('staff', 'name', fn($query) => $query->role('staff'))
                    ->searchable()
                    ->hidden($isStaff && ! $hasViewAll),
```

The `DeleteBulkAction::make()->hidden($isStaff)` at the bottom stays as-is (staff can never bulk-delete regardless of permissions).

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "appointments.create|view_all|own appointments|all appointments"
```

Expected: PASS.

- [ ] **Step 5: Run existing appointment tests for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentResourceStaffEditTest.php
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: apply appointments.view_all and appointments.create permission checks"
```

---

## Task 4: CalendarWidget + AppointmentCalendar page

**Files:**
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`
- Modify: `app/Filament/Pages/AppointmentCalendar.php`
- Test: `tests/Feature/Filament/AppointmentCalendarTest.php` (add tests)

Context: `fetchEvents()` in the widget currently hard-filters by `staff_id` for all staff. Need to skip the filter for staff with `appointments.view_all`. Similarly `authorizeAppointmentAccess()` should allow staff with `view_all` to interact with any appointment on the calendar. The `filtersForm()` in the page should show the staff dropdown for staff with `view_all`.

- [ ] **Step 1: Read the existing AppointmentCalendarTest.php to understand test patterns**

```bash
cat tests/Feature/Filament/AppointmentCalendarTest.php
```

- [ ] **Step 2: Write the failing test**

In `tests/Feature/Filament/AppointmentCalendarTest.php`, add at the end (after the last existing test):

```php
it('staff with view_all sees all appointments in calendar', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'appointments.view_all', 'guard_name' => 'web']);

    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.view_all');

    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $ownAppt   = Appointment::factory()->create(['staff_id' => $staff->id,      'user_id' => $customer->id, 'scheduled_date' => now()]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id, 'scheduled_date' => now()]);

    $this->actingAs($staff);

    $widget = new \App\Filament\Widgets\AppointmentCalendarWidget();

    $events = $widget->fetchEvents([
        'start' => now()->subDay()->toIso8601String(),
        'end'   => now()->addDay()->toIso8601String(),
    ]);

    $eventIds = array_column($events, 'id');
    expect($eventIds)->toContain($ownAppt->id);
    expect($eventIds)->toContain($otherAppt->id);
});

it('staff without view_all sees only own appointments in calendar', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'appointments.view_all', 'guard_name' => 'web']);

    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');

    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $ownAppt   = Appointment::factory()->create(['staff_id' => $staff->id,      'user_id' => $customer->id, 'scheduled_date' => now()]);
    $otherAppt = Appointment::factory()->create(['staff_id' => $otherStaff->id, 'user_id' => $customer->id, 'scheduled_date' => now()]);

    $this->actingAs($staff);

    $widget = new \App\Filament\Widgets\AppointmentCalendarWidget();

    $events = $widget->fetchEvents([
        'start' => now()->subDay()->toIso8601String(),
        'end'   => now()->addDay()->toIso8601String(),
    ]);

    $eventIds = array_column($events, 'id');
    expect($eventIds)->toContain($ownAppt->id);
    expect($eventIds)->not->toContain($otherAppt->id);
});
```

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "view_all sees all|without view_all sees only"
```

Expected: FAIL.

- [ ] **Step 4: Update AppointmentCalendarWidget::fetchEvents()**

In `app/Filament/Widgets/AppointmentCalendarWidget.php`, replace the role-based section inside `fetchEvents()` (lines 62-68, the `if ($user->isAdmin())... elseif ($user->isStaff())...` block):

```php
        if ($user->isAdmin()) {
            if (!empty($this->filterStaff)) {
                $query->whereIn('staff_id', $this->filterStaff);
            }
        } elseif ($user->isStaff()) {
            if ($user->can('appointments.view_all')) {
                if (!empty($this->filterStaff)) {
                    $query->whereIn('staff_id', $this->filterStaff);
                }
            } else {
                $query->where('staff_id', $user->id);
            }
        }
```

- [ ] **Step 5: Update AppointmentCalendarWidget::authorizeAppointmentAccess()**

Replace the `isStaff()` branch inside `authorizeAppointmentAccess()`:

```php
        if ($user->isStaff() && $appointmentId) {
            if ($user->can('appointments.view_all')) {
                return Appointment::where('id', $appointmentId)->exists();
            }
            return Appointment::where('id', $appointmentId)
                ->where('staff_id', $user->id)
                ->exists();
        }
```

- [ ] **Step 6: Update AppointmentCalendar::filtersForm() to show staff filter for view_all staff**

In `app/Filament/Pages/AppointmentCalendar.php`, replace the condition that adds the staff filter:

```php
        if ($user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.view_all'))) {
            $fields[] = Select::make('filterStaff')
                ->label('Staff')
                ->options(fn() => User::role('staff')->orderBy('name')->pluck('name', 'id'))
                ->placeholder('Tutti')
                ->multiple()
                ->live();
        }
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "view_all sees all|without view_all sees only"
```

Expected: PASS.

- [ ] **Step 8: Run full calendar test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: all pass.

- [ ] **Step 9: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php app/Filament/Pages/AppointmentCalendar.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: apply appointments.view_all permission to calendar widget and page"
```

---

## Task 5: CustomerResource, PaymentResource, ReportPage

**Files:**
- Modify: `app/Filament/Resources/CustomerResource.php`
- Modify: `app/Filament/Resources/PaymentResource.php`
- Modify: `app/Filament/Pages/ReportPage.php`
- Test: `tests/Feature/Filament/StaffPermissionsTest.php` (add tests)

- [ ] **Step 1: Write the failing tests**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Filament\Resources\PaymentResource\Pages\ListPayments;
use App\Filament\Pages\ReportPage;

it('staff without customers.view cannot access customer list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);
    expect(\App\Filament\Resources\CustomerResource::canViewAny())->toBeFalse();
});

it('staff with customers.view can access customer list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('customers.view');
    $this->actingAs($staff);
    expect(\App\Filament\Resources\CustomerResource::canViewAny())->toBeTrue();
});

it('staff without payments.manage cannot access payment list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);
    expect(\App\Filament\Resources\PaymentResource::canViewAny())->toBeFalse();
});

it('staff with payments.manage can access payment list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('payments.manage');
    $this->actingAs($staff);
    expect(\App\Filament\Resources\PaymentResource::canViewAny())->toBeTrue();
});

it('staff without reports.view cannot access report page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);
    expect(ReportPage::canAccess())->toBeFalse();
});

it('staff with reports.view can access report page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->givePermissionTo('reports.view');
    $this->actingAs($staff);
    expect(ReportPage::canAccess())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "customers.view|payments.manage|reports.view"
```

Expected: FAIL.

- [ ] **Step 3: Update CustomerResource**

In `app/Filament/Resources/CustomerResource.php`, replace the three authorization methods:

```php
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('customers.view')) ?? false;
    }

    public static function canView(Model $record): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('customers.view')) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('customers.view')) ?? false;
    }
```

- [ ] **Step 4: Update PaymentResource**

In `app/Filament/Resources/PaymentResource.php`, replace `canViewAny()`:

```php
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('payments.manage')) ?? false;
    }
```

- [ ] **Step 5: Update ReportPage**

In `app/Filament/Pages/ReportPage.php`, replace `canAccess()`:

```php
    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user?->isAdmin() || ($user?->isStaff() && $user->can('reports.view')) ?? false;
    }
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php --filter "customers.view|payments.manage|reports.view"
```

Expected: PASS.

- [ ] **Step 7: Run existing resource tests for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/CustomerResourceTest.php tests/Feature/Filament/ResourcesTest.php
```

Expected: all pass.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/CustomerResource.php app/Filament/Resources/PaymentResource.php app/Filament/Pages/ReportPage.php tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: apply customers.view, payments.manage, reports.view permission checks"
```

---

## Final verification

After all tasks, run the full test suite:

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass.
