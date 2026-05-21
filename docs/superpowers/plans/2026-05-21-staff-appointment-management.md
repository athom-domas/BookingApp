# Staff Appointment Management Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow staff to open the edit page for their own non-completed appointments to change the status and register payment.

**Architecture:** Four targeted changes across two existing files — `AppointmentResource.php` and `EditAppointment.php`. No new files, no new abstractions.

**Tech Stack:** Laravel 13, Filament 4, Pest PHP, Docker (all commands via `docker-compose run --rm app`)

---

### Task 1: Write failing tests

**Files:**
- Create: `tests/Feature/Filament/AppointmentResourceStaffEditTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Filament\Resources\AppointmentResource;
use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('staff can access edit page for their own pending appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertSuccessful();
});

it('staff can access edit page for their own confirmed appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'confirmed',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertSuccessful();
});

it('staff cannot access edit page for another staff appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $otherStaff = User::factory()->create();
    $otherStaff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $otherStaff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff cannot access edit page for a completed appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'completed',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff cannot access edit page for a cancelled appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'cancelled',
    ]);

    $this->actingAs($staff)
        ->get(AppointmentResource::getUrl('edit', ['record' => $appointment]))
        ->assertForbidden();
});

it('staff can change status on their own appointment', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $appointment = Appointment::factory()->create([
        'staff_id' => $staff->id,
        'user_id'  => $customer->id,
        'status'   => 'pending',
    ]);

    $this->actingAs($staff);

    Livewire::test(EditAppointment::class, ['record' => $appointment->id])
        ->set('data.status', 'confirmed')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($appointment->refresh()->status)->toBe('confirmed');
});
```

- [ ] **Step 2: Run to verify all tests fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentResourceStaffEditTest.php
```

Expected: all 6 tests FAIL (staff currently redirected or forbidden on all edit attempts)

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/Filament/AppointmentResourceStaffEditTest.php
git commit -m "test: add failing tests for staff appointment edit access"
```

---

### Task 2: Fix `canEdit()` in AppointmentResource

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource.php:37-44`

- [ ] **Step 1: Replace `canEdit()`**

Current:
```php
public static function canEdit($record): bool
{
    if (auth()->user()?->isStaff()) {
        return false;
    }

    return ! in_array($record->status, ['completed', 'cancelled']);
}
```

New:
```php
public static function canEdit($record): bool
{
    if (auth()->user()?->isStaff()) {
        return $record->staff_id === auth()->id()
            && ! in_array($record->status, ['completed', 'cancelled']);
    }

    return ! in_array($record->status, ['completed', 'cancelled']);
}
```

- [ ] **Step 2: Fix `authorizeAccess()` in EditAppointment**

In `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`, replace:

```php
protected function authorizeAccess(): void
{
    if (auth()->user()?->isStaff()) {
        $this->redirect($this->getResource()::getUrl('index'));
    }
}
```

With:

```php
protected function authorizeAccess(): void
{
    parent::authorizeAccess();
}
```

- [ ] **Step 3: Run the access tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentResourceStaffEditTest.php --filter "staff can access|staff cannot access"
```

Expected: the 5 access tests PASS, the status change test still FAIL

---

### Task 3: Enable form actions and fix staff_id field

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php:23-30`
- Modify: `app/Filament/Resources/AppointmentResource.php:74-79`

- [ ] **Step 1: Remove the staff check from `getFormActions()`**

`EditAppointment::getFormActions()` currently only checks status — no staff check exists there. The form actions work once `authorizeAccess()` is fixed. No change needed here.

Verify by reading the method:

```php
protected function getFormActions(): array
{
    if (in_array($this->record->status, ['completed', 'cancelled'])) {
        return [];
    }

    return parent::getFormActions();
}
```

This is already correct — staff accessing a pending/confirmed appointment will get the save button.

- [ ] **Step 2: Disable `staff_id` field for staff users**

In `app/Filament/Resources/AppointmentResource.php`, replace the `staff_id` field's `disabled()`:

```php
->disabled(fn ($record) => in_array($record?->status, ['completed', 'cancelled'])),
```

With:

```php
->disabled(fn ($record) => auth()->user()?->isStaff() || in_array($record?->status, ['completed', 'cancelled'])),
```

- [ ] **Step 3: Make `register_payment` action visible for staff**

In the same file, replace the `->visible()` on the `register_payment` action:

```php
->visible(fn(Appointment $record): bool => ! $isStaff && ! in_array($record->status, ['pending', 'completed', 'cancelled']) && (! $record->payment || $record->payment->status !== 'completed')),
```

With:

```php
->visible(fn(Appointment $record): bool => ! in_array($record->status, ['pending', 'completed', 'cancelled']) && (! $record->payment || $record->payment->status !== 'completed')),
```

- [ ] **Step 4: Run the full test file**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentResourceStaffEditTest.php
```

Expected: all 6 tests PASS

- [ ] **Step 5: Run the full test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests PASS

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php \
        app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php
git commit -m "feat: allow staff to edit status and payment on own appointments"
```
