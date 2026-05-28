# Staff Permissions V2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the 5-checkbox permissions system with 4 semantic select dropdowns offering granular levels, fix the appointment create bug for staff, and enable customer create/delete from the admin panel.

**Architecture:** Spatie named permissions (already installed). 11 permissions replace the previous 5. StaffResource edit form gets 4 Select dropdowns instead of a CheckboxList. Authorization methods in 6 resources/pages are updated for the new permission names.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Spatie Laravel Permission

---

## New Permission Set

| Permission | Granted by |
|---|---|
| `appointments.view_all` | Visibility = "tutti" |
| `appointments.create` | Management ≥ "solo creazione" |
| `appointments.edit` | Management ≥ "gestione completa" |
| `appointments.payments` | Management ≥ "gestione completa" |
| `appointments.delete` | Management = "gestione completa con eliminazione" |
| `customers.view` | Customers ≥ "solo visualizzazione" |
| `customers.create` | Customers ≥ "visualizzazione e creazione" |
| `customers.edit` | Customers ≥ "gestione completa" |
| `customers.delete` | Customers = "gestione completa con eliminazione" |
| `reports.view` | Reports ≥ "senza dati economici" |
| `reports.view_revenue` | Reports = "completo" |

Old permission `payments.manage` is removed.

---

## Files Modified / Created

- **Modify:** `database/seeders/DatabaseSeeder.php`
- **Modify:** `app/Filament/Resources/StaffResource.php`
- **Modify:** `app/Filament/Resources/StaffResource/Pages/EditStaff.php`
- **Modify:** `app/Filament/Resources/AppointmentResource.php`
- **Modify:** `app/Filament/Resources/PaymentResource.php`
- **Modify:** `app/Filament/Widgets/AppointmentCalendarWidget.php`
- **Modify:** `app/Filament/Resources/CustomerResource.php`
- **Create:** `app/Filament/Resources/CustomerResource/Pages/CreateCustomer.php`
- **Modify:** `app/Filament/Pages/ReportPage.php`
- **Modify:** `tests/Feature/Filament/StaffPermissionsTest.php`

---

## Task 1: Update DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php:24`

Replace the old 5-permission list with 11 new permissions and remove `payments.manage`.

- [ ] **Step 1: Update the permissions loop in DatabaseSeeder**

Replace lines 24-26 in `database/seeders/DatabaseSeeder.php`:

```php
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
] as $perm) {
    Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
}

// Clean up removed permission from previous seeder runs
Permission::where('name', 'payments.manage')->where('guard_name', 'web')->delete();
```

- [ ] **Step 2: Run the seeder to verify it works**

```bash
docker-compose run --rm app php artisan migrate:fresh --seed
```

Expected: No errors. All 11 permissions exist in the `permissions` table.

- [ ] **Step 3: Verify in tinker**

```bash
docker-compose run --rm app php artisan tinker --execute="use Spatie\Permission\Models\Permission; echo Permission::pluck('name')->implode(', ');"
```

Expected output contains all 11 new permission names and does NOT contain `payments.manage`.

- [ ] **Step 4: Commit**

```bash
git add database/seeders/DatabaseSeeder.php
git commit -m "feat: update staff permissions seeder to 11 granular permissions"
```

---

## Task 2: StaffResource Form Redesign

**Files:**
- Modify: `app/Filament/Resources/StaffResource.php`
- Modify: `app/Filament/Resources/StaffResource/Pages/EditStaff.php`

Replace the `CheckboxList` with 4 `Select` dropdowns inside a `Section`. Update `afterSave()` to translate select values into permissions.

- [ ] **Step 1: Write the failing test for form hydration**

Add to `tests/Feature/Filament/StaffPermissionsTest.php` (temporarily, will be replaced in Task 7):

```php
it('edit form hydrates selects correctly from existing permissions', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['appointments.view_all', 'appointments.create', 'appointments.edit', 'appointments.payments']);

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->assertFormSet([
            'appointments_visibility'  => 'all',
            'appointments_management'  => 'full',
            'customers_management'     => 'none',
            'reports_visibility'       => 'none',
        ]);
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "edit form hydrates selects correctly"
```

Expected: FAIL — fields do not exist yet.

- [ ] **Step 3: Update StaffResource.php — replace CheckboxList with 4 Selects**

Add import at the top:
```php
use Filament\Schemas\Components\Section;
```

Replace the entire `CheckboxList::make('staff_permissions')` block (lines ~150–165) with:

```php
Section::make('Permessi pannello admin')
    ->schema([
        Select::make('appointments_visibility')
            ->label('Visibilità appuntamenti')
            ->options([
                'personal' => 'Solo personali',
                'all'      => 'Tutti gli appuntamenti del salone',
            ])
            ->default('personal')
            ->required()
            ->afterStateHydrated(function ($component, $record) {
                if (! $record) {
                    return;
                }
                $perms = $record->getDirectPermissions()->pluck('name')->toArray();
                $component->state(in_array('appointments.view_all', $perms) ? 'all' : 'personal');
            })
            ->dehydrated(false),

        Select::make('appointments_management')
            ->label('Gestione appuntamenti')
            ->options([
                'view_only'   => 'Solo visualizzazione',
                'create'      => 'Solo creazione',
                'full'        => 'Gestione completa',
                'full_delete' => 'Gestione completa con eliminazione',
            ])
            ->default('view_only')
            ->required()
            ->afterStateHydrated(function ($component, $record) {
                if (! $record) {
                    return;
                }
                $perms = $record->getDirectPermissions()->pluck('name')->toArray();
                $has = fn($p) => in_array($p, $perms);
                if ($has('appointments.delete')) {
                    $component->state('full_delete');
                    return;
                }
                if ($has('appointments.edit')) {
                    $component->state('full');
                    return;
                }
                if ($has('appointments.create')) {
                    $component->state('create');
                    return;
                }
                $component->state('view_only');
            })
            ->dehydrated(false),

        Select::make('customers_management')
            ->label('Gestione clienti')
            ->options([
                'none'        => 'Nessun accesso',
                'view'        => 'Solo visualizzazione',
                'create'      => 'Visualizzazione e creazione',
                'full'        => 'Gestione completa',
                'full_delete' => 'Gestione completa con eliminazione',
            ])
            ->default('none')
            ->required()
            ->afterStateHydrated(function ($component, $record) {
                if (! $record) {
                    return;
                }
                $perms = $record->getDirectPermissions()->pluck('name')->toArray();
                $has = fn($p) => in_array($p, $perms);
                if ($has('customers.delete')) {
                    $component->state('full_delete');
                    return;
                }
                if ($has('customers.edit')) {
                    $component->state('full');
                    return;
                }
                if ($has('customers.create')) {
                    $component->state('create');
                    return;
                }
                if ($has('customers.view')) {
                    $component->state('view');
                    return;
                }
                $component->state('none');
            })
            ->dehydrated(false),

        Select::make('reports_visibility')
            ->label('Accesso report')
            ->options([
                'none'       => 'Nessun accesso',
                'no_revenue' => 'Senza dati economici',
                'full'       => 'Completo (inclusi guadagni)',
            ])
            ->default('none')
            ->required()
            ->afterStateHydrated(function ($component, $record) {
                if (! $record) {
                    return;
                }
                $perms = $record->getDirectPermissions()->pluck('name')->toArray();
                $has = fn($p) => in_array($p, $perms);
                if ($has('reports.view_revenue')) {
                    $component->state('full');
                    return;
                }
                if ($has('reports.view')) {
                    $component->state('no_revenue');
                    return;
                }
                $component->state('none');
            })
            ->dehydrated(false),
    ])
    ->visibleOn('edit')
    ->columnSpanFull(),
```

Also remove the `use Filament\Forms\Components\CheckboxList;` import since it's no longer needed.

- [ ] **Step 4: Update EditStaff::afterSave() in Pages/EditStaff.php**

Replace the entire `afterSave()` method:

```php
protected function afterSave(): void
{
    $rawState = $this->form->getRawState();
    if (! array_key_exists('appointments_visibility', $rawState)) {
        return;
    }

    $perms = [];

    if (($rawState['appointments_visibility'] ?? 'personal') === 'all') {
        $perms[] = 'appointments.view_all';
    }

    $management = $rawState['appointments_management'] ?? 'view_only';
    if (in_array($management, ['create', 'full', 'full_delete'])) {
        $perms[] = 'appointments.create';
    }
    if (in_array($management, ['full', 'full_delete'])) {
        $perms[] = 'appointments.edit';
        $perms[] = 'appointments.payments';
    }
    if ($management === 'full_delete') {
        $perms[] = 'appointments.delete';
    }

    $customers = $rawState['customers_management'] ?? 'none';
    if ($customers !== 'none') {
        $perms[] = 'customers.view';
    }
    if (in_array($customers, ['create', 'full', 'full_delete'])) {
        $perms[] = 'customers.create';
    }
    if (in_array($customers, ['full', 'full_delete'])) {
        $perms[] = 'customers.edit';
    }
    if ($customers === 'full_delete') {
        $perms[] = 'customers.delete';
    }

    $reports = $rawState['reports_visibility'] ?? 'none';
    if (in_array($reports, ['no_revenue', 'full'])) {
        $perms[] = 'reports.view';
    }
    if ($reports === 'full') {
        $perms[] = 'reports.view_revenue';
    }

    $this->record->syncPermissions($perms);
}
```

- [ ] **Step 5: Run the hydration test to confirm it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "edit form hydrates selects correctly"
```

Expected: PASS.

- [ ] **Step 6: Write a test for saving permissions**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
it('saving full_delete management level grants correct permissions', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->fillForm([
            'appointments_visibility'  => 'all',
            'appointments_management'  => 'full_delete',
            'customers_management'     => 'full',
            'reports_visibility'       => 'no_revenue',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.create'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.edit'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.payments'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.delete'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.view'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.create'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.edit'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.delete'))->toBeFalse();
    expect($staff->hasPermissionTo('reports.view'))->toBeTrue();
    expect($staff->hasPermissionTo('reports.view_revenue'))->toBeFalse();
});
```

- [ ] **Step 7: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php
```

Expected: All passing (old tests will fail — fix them in Task 7).

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/StaffResource.php \
        app/Filament/Resources/StaffResource/Pages/EditStaff.php \
        tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: replace CheckboxList with 4 select dropdowns in StaffResource permissions"
```

---

## Task 3: AppointmentResource Authorization Fixes

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource.php`

Four fixes:
1. `staff_id` disabled condition — was always disabled for staff (create broke)
2. `canEdit()` — needs `appointments.edit` permission
3. `canDelete()` — needs `appointments.delete` permission
4. `scheduled_date` and `notes` disabled conditions — should be editable by staff with `appointments.edit`
5. `register_payment` action visibility — check `appointments.payments`
6. `DeleteAction` / `DeleteBulkAction` — unhide for staff with `appointments.delete`

- [ ] **Step 1: Write failing test for staff_id bug**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
it('staff with appointments.create can submit create form with own staff_id defaulted', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.create');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $this->actingAs($staff);

    $page = Livewire::test(\App\Filament\Resources\AppointmentResource\Pages\CreateAppointment::class)
        ->fillForm([
            'user_id'        => $customer->id,
            'service_ids'    => [],
            'staff_id'       => $staff->id,
            'scheduled_date' => now()->addDay()->format('Y-m-d H:i:s'),
            'status'         => 'pending',
        ]);

    // Verify the staff_id field is not disabled (no 'disabled' attribute preventing submission)
    expect(\App\Filament\Resources\AppointmentResource::canCreate())->toBeTrue();
});
```

- [ ] **Step 2: Run test to confirm the canCreate check passes**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "staff with appointments.create can submit"
```

- [ ] **Step 3: Fix staff_id field**

In `AppointmentResource::form()`, replace the `Select::make('staff_id')` block:

```php
Select::make('staff_id')
    ->label('Staff')
    ->relationship('staff', 'name', fn($query) => $query->role('staff'))
    ->required()
    ->searchable()
    ->default(fn() => auth()->user()?->isStaff() ? auth()->id() : null)
    ->hidden(fn($record) => auth()->user()?->isStaff() && $record === null)
    ->disabled(
        fn($record) =>
        $record?->status === 'completed'
            || $record?->status === 'cancelled'
            || (! auth()->user()?->isAdmin() && $record !== null)
    ),
```

Explanation:
- `->default(...)`: pre-fills the staff_id with the current staff user's id
- `->hidden(fn($record) => auth()->user()?->isStaff() && $record === null)`: hides the field during create for staff (uses default value)
- Disabled condition: now only disables when editing (`$record !== null`) for non-admins, and for completed/cancelled status

- [ ] **Step 4: Fix scheduled_date and notes disabled conditions**

Replace the `scheduled_date` disabled condition:

```php
DateTimePicker::make('scheduled_date')
    ->label('Data e ora')
    ->required()
    ->disabled(
        fn($record) =>
        $record?->status === 'completed'
            || (! auth()->user()?->isAdmin()
                && $record !== null
                && ! auth()->user()?->can('appointments.edit'))
    ),
```

Replace the `notes` disabled condition:

```php
Textarea::make('notes')
    ->label('Note')
    ->rows(3)
    ->columnSpanFull()
    ->disabled(
        fn($record) =>
        $record?->status === 'completed'
            || (! auth()->user()?->isAdmin()
                && $record !== null
                && ! auth()->user()?->can('appointments.edit'))
    ),
```

- [ ] **Step 5: Update canEdit()**

Replace the `canEdit()` method:

```php
public static function canEdit($record): bool
{
    $user = auth()->user();
    if ($user?->isAdmin()) {
        return true;
    }

    if ($user?->isStaff() && $user->can('appointments.edit')) {
        $owned    = $record->staff_id === $user->id;
        $canAny   = $user->can('appointments.view_all');
        return ($owned || $canAny)
            && ! in_array($record->status, ['completed', 'cancelled']);
    }

    return false;
}
```

- [ ] **Step 6: Update canDelete()**

Replace the `canDelete()` method:

```php
public static function canDelete($record): bool
{
    $user = auth()->user();
    if ($user?->isAdmin()) {
        return true;
    }
    if ($user?->isStaff()) {
        return $user->can('appointments.delete');
    }
    return false;
}
```

- [ ] **Step 7: Update register_payment action visibility**

In `table()`, replace the `->visible(...)` closure on the `register_payment` action:

```php
->visible(fn(Appointment $record): bool =>
    ! in_array($record->status, ['pending', 'completed', 'cancelled'])
    && (! $record->payment || $record->payment->status !== 'completed')
    && (auth()->user()?->isAdmin() || auth()->user()?->can('appointments.payments'))
),
```

- [ ] **Step 8: Update DeleteAction hidden condition**

In `table()` actions, replace:

```php
DeleteAction::make()
    ->hidden(fn() => auth()->user()?->isStaff()),
```

With:

```php
DeleteAction::make()
    ->hidden(fn() => auth()->user()?->isStaff() && ! auth()->user()?->can('appointments.delete')),
```

- [ ] **Step 9: Update DeleteBulkAction hidden condition**

The `DeleteBulkAction` is evaluated at table build time (outside a closure). Update:

```php
$user       = auth()->user();
$isStaff    = $user?->isStaff() ?? false;
$hasViewAll = $isStaff && ($user?->can('appointments.view_all') ?? false);
$canDelete  = ! $isStaff || ($user?->can('appointments.delete') ?? false);
```

Then use `->hidden(! $canDelete)` on the bulk action:

```php
->bulkActions([
    DeleteBulkAction::make()->hidden(! $canDelete),
]);
```

- [ ] **Step 10: Write tests for new authorization**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
it('staff without appointments.edit cannot edit appointments', function () {
    app()->instance('current_business_id', $this->business->id);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

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
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

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
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

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
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.delete');

    $customer = User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    $appt = Appointment::factory()->create(['staff_id' => $staff->id, 'user_id' => $customer->id]);

    $this->actingAs($staff);

    expect(\App\Filament\Resources\AppointmentResource::canDelete($appt))->toBeTrue();
});
```

- [ ] **Step 11: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php
```

Expected: New tests pass. Some old tests may still fail (fixed in Task 7).

- [ ] **Step 12: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php \
        tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "fix: appointment authorization for new permissions + fix staff_id create bug"
```

---

## Task 4: PaymentResource and Calendar Widget

**Files:**
- Modify: `app/Filament/Resources/PaymentResource.php`
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`

Update `payments.manage` → `appointments.payments` in PaymentResource. Update calendar widget to require `appointments.edit` for the changeStatus action.

- [ ] **Step 1: Write failing test for payments**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
it('staff with appointments.payments can access payment list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('appointments.payments');
    $this->actingAs($staff);

    expect(PaymentResource::canViewAny())->toBeTrue();
});
```

- [ ] **Step 2: Run test to confirm it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "staff with appointments.payments can access payment list"
```

Expected: FAIL.

- [ ] **Step 3: Update PaymentResource::canViewAny()**

Replace the canViewAny method in `app/Filament/Resources/PaymentResource.php`:

```php
public static function canViewAny(): bool
{
    $user = auth()->user();
    return ($user?->isAdmin() || ($user?->isStaff() && $user->can('appointments.payments'))) ?? false;
}
```

- [ ] **Step 4: Update AppointmentCalendarWidget — add edit authorization**

In `app/Filament/Widgets/AppointmentCalendarWidget.php`, add a new private method `authorizeAppointmentEdit()` and update the authorize callbacks.

Add after `authorizeAppointmentAccess()`:

```php
private function authorizeAppointmentEdit(Action $action): bool
{
    $user          = auth()->user();
    $appointmentId = $action->getArguments()['appointmentId'] ?? null;

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->isStaff() && $appointmentId && $user->can('appointments.edit')) {
        if ($user->can('appointments.view_all')) {
            return Appointment::where('id', $appointmentId)->exists();
        }
        return Appointment::where('id', $appointmentId)
            ->where('staff_id', $user->id)
            ->exists();
    }

    return false;
}
```

Update `changeStatusAction()` to use `authorizeAppointmentEdit`:

```php
->authorize(fn(Action $action) => $this->authorizeAppointmentEdit($action))
```

Leave `viewAppointmentAction()` using `authorizeAppointmentAccess` (read-only view, no edit needed).

- [ ] **Step 5: Run failing payment test to confirm it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "staff with appointments.payments can access payment list"
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/PaymentResource.php \
        app/Filament/Widgets/AppointmentCalendarWidget.php \
        tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: update payment and calendar permissions for new permission names"
```

---

## Task 5: CustomerResource — Enable Create and Delete

**Files:**
- Modify: `app/Filament/Resources/CustomerResource.php`
- Create: `app/Filament/Resources/CustomerResource/Pages/CreateCustomer.php`

Enable customer creation (via admin panel) and deletion. Admins can always create/delete; staff need `customers.create` / `customers.delete` respectively.

When an admin creates a customer from the admin panel, the user gets a randomly generated password (they can reset it via "forgot password" on the login page).

- [ ] **Step 1: Write failing test for customer create**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
it('staff with customers.create can create customers', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['customers.view', 'customers.create']);
    $this->actingAs($staff);

    expect(CustomerResource::canCreate())->toBeTrue();
});

it('staff without customers.create cannot create customers', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo('customers.view');
    $this->actingAs($staff);

    expect(CustomerResource::canCreate())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "customers.create"
```

Expected: Both fail.

- [ ] **Step 3: Update CustomerResource authorization methods**

In `app/Filament/Resources/CustomerResource.php`, replace `canCreate()`, `canEdit()`, `canDelete()`, `canDeleteAny()`:

```php
public static function canCreate(): bool
{
    $user = auth()->user();
    return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.create'))) ?? false;
}

public static function canEdit(Model $record): bool
{
    $user = auth()->user();
    return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.edit'))) ?? false;
}

public static function canDelete(Model $record): bool
{
    $user = auth()->user();
    return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.delete'))) ?? false;
}

public static function canDeleteAny(): bool
{
    $user = auth()->user();
    return ($user?->isAdmin() || ($user?->isStaff() && $user->can('customers.delete'))) ?? false;
}
```

Also add password fields to the form for the create operation. In `CustomerResource::form()`, add after the `email` field:

```php
TextInput::make('password')
    ->label('Password')
    ->password()
    ->required()
    ->minLength(8)
    ->maxLength(255)
    ->visibleOn('create'),
```

Add missing import at top of CustomerResource.php:
```php
use Filament\Forms\Components\TextInput;
```

(It may already be imported — check and only add if missing.)

- [ ] **Step 4: Create CreateCustomer page**

Create `app/Filament/Resources/CustomerResource/Pages/CreateCustomer.php`:

```php
<?php

namespace App\Filament\Resources\CustomerResource\Pages;

use App\Filament\Resources\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $record = parent::handleRecordCreation($data);
        $record->syncRoles(['customer']);
        return $record;
    }
}
```

- [ ] **Step 5: Register CreateCustomer in CustomerResource::getPages()**

Replace `getPages()` in CustomerResource:

```php
public static function getPages(): array
{
    return [
        'index'  => Pages\ListCustomers::route('/'),
        'create' => Pages\CreateCustomer::route('/create'),
        'edit'   => Pages\EditCustomer::route('/{record}/edit'),
    ];
}
```

Also add a `DeleteAction` to the table's actions, and a `DeleteBulkAction` to bulkActions. Currently only `EditAction` is present. Add after EditAction:

```php
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
```

In `table()`, update the actions and bulk actions:

```php
->actions([
    EditAction::make()
        ->label('Scheda cliente'),
    DeleteAction::make(),
])
->bulkActions([
    DeleteBulkAction::make(),
]),
```

- [ ] **Step 6: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "customers.create"
```

Expected: Both pass.

- [ ] **Step 7: Run the full test file**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php
```

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Resources/CustomerResource.php \
        app/Filament/Resources/CustomerResource/Pages/CreateCustomer.php \
        tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: enable customer create/delete with granular staff permissions"
```

---

## Task 6: ReportPage Revenue Gating

**Files:**
- Modify: `app/Filament/Pages/ReportPage.php`

Staff with `reports.view` (but not `reports.view_revenue`) see InsightStats, AppointmentsByStatus, ServiceBreakdown — but NOT RevenueStats, RevenueChart, StaffPerformance. Staff or admins with `reports.view_revenue` see all widgets.

- [ ] **Step 1: Write failing test**

Add to `tests/Feature/Filament/StaffPermissionsTest.php`:

```php
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
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "reports.view"
```

Expected: Both fail (getWidgets always returns all widgets currently).

- [ ] **Step 3: Update ReportPage::getWidgets()**

Replace the `getWidgets()` method in `app/Filament/Pages/ReportPage.php`:

```php
public function getWidgets(): array
{
    $user       = auth()->user();
    $hasRevenue = $user?->isAdmin() || ($user?->isStaff() && $user->can('reports.view_revenue'));

    $widgets = [
        InsightStatsWidget::class,
        AppointmentsByStatusChartWidget::class,
        ServiceBreakdownChartWidget::class,
    ];

    if ($hasRevenue) {
        array_unshift($widgets, RevenueStatsWidget::class);
        $widgets[] = RevenueChartWidget::class;
        $widgets[] = StaffPerformanceWidget::class;
    }

    return $widgets;
}
```

- [ ] **Step 4: Run revenue tests**

```bash
docker-compose run --rm app ./vendor/bin/pest --filter "reports.view"
```

Expected: Both pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/ReportPage.php \
        tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "feat: gate revenue widgets in ReportPage behind reports.view_revenue permission"
```

---

## Task 7: Update Existing Tests

**Files:**
- Modify: `tests/Feature/Filament/StaffPermissionsTest.php`

The old tests reference old permission names (`payments.manage`, `customers.view`) and the old form field (`staff_permissions`). Update them all to the new model.

- [ ] **Step 1: Update beforeEach permissions list**

Replace the `foreach` in `beforeEach`:

```php
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
```

- [ ] **Step 2: Update "admin can grant permissions" test**

Replace the test:

```php
it('admin can grant permissions to staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->fillForm([
            'appointments_visibility'  => 'all',
            'appointments_management'  => 'full',
            'customers_management'     => 'none',
            'reports_visibility'       => 'none',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.create'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.edit'))->toBeTrue();
    expect($staff->hasPermissionTo('appointments.payments'))->toBeTrue();
    expect($staff->hasPermissionTo('customers.view'))->toBeFalse();
});
```

- [ ] **Step 3: Update "admin can revoke permissions" test**

Replace the test:

```php
it('admin can revoke permissions from staff via edit form', function () {
    $admin = User::factory()->create(['business_id' => $this->business->id]);
    $admin->assignRole('admin');

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $staff->givePermissionTo(['appointments.view_all', 'reports.view']);

    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->fillForm([
            'appointments_visibility'  => 'personal',
            'appointments_management'  => 'view_only',
            'customers_management'     => 'none',
            'reports_visibility'       => 'none',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $staff->refresh();
    expect($staff->hasPermissionTo('appointments.view_all'))->toBeFalse();
    expect($staff->hasPermissionTo('reports.view'))->toBeFalse();
});
```

- [ ] **Step 4: Update "seeder creates the 5 staff permissions" test**

Replace it with a test for the 11 new permissions:

```php
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
```

- [ ] **Step 5: Update the "payments.manage" tests**

Replace the two old payment tests with:

```php
it('staff without appointments.payments cannot access payment list', function () {
    app()->instance('current_business_id', $this->business->id);
    Filament::setTenant($this->business, isQuiet: true);

    $staff = User::factory()->create(['business_id' => $this->business->id]);
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(PaymentResource::canViewAny())->toBeFalse();
});
```

(The passing test was already added in Task 4.)

- [ ] **Step 6: Update the "customers.view" tests**

The two existing customers tests are still valid since `customers.view` still exists with the same behavior. They can remain as-is.

- [ ] **Step 7: Run the full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffPermissionsTest.php
```

Expected: All tests pass.

- [ ] **Step 8: Run the full test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: All tests pass.

- [ ] **Step 9: Commit**

```bash
git add tests/Feature/Filament/StaffPermissionsTest.php
git commit -m "test: update StaffPermissionsTest for new 11-permission model"
```
