# Calendar Multi-Select Filters Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the single staff select with four multi-select filters (staff, status, service, customer) rendered above the calendar for both admin and staff roles.

**Architecture:** Widget filter state changes from `?int $staffFilter` to four `array` properties. The page dispatches a single `calendar-filters-updated` event whenever any filter changes. Calendar moves from `getHeaderWidgets()` to `getFooterWidgets()` so the filter form (page content slot) always renders above it.

**Tech Stack:** Laravel 13, Filament 4, Livewire 3, Saade FilamentFullCalendar, MySQL 8 (JSON_OVERLAPS for service_ids JSON column)

---

### Task 1: Update widget — filter state, event listener, query

**Files:**
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`
- Modify: `tests/Feature/Filament/AppointmentCalendarTest.php`

- [ ] **Step 1: Update existing filter test and add new filter tests**

In `tests/Feature/Filament/AppointmentCalendarTest.php`, replace the existing `filtra gli eventi per staff` test and add three new ones:

```php
it('filtra gli eventi per staff quando admin imposta filterStaff', function () use (&$fetchRange) {
    $admin  = User::factory()->create()->assignRole('admin');
    $staff1 = User::factory()->create()->assignRole('staff');
    $staff2 = User::factory()->create()->assignRole('staff');

    $own = Appointment::factory()->create(['staff_id' => $staff1->id, 'scheduled_date' => now()->addDays(1)]);
    Appointment::factory()->create(['staff_id' => $staff2->id, 'scheduled_date' => now()->addDays(2)]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('filterStaff', [$staff1->id])
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});

it('filtra gli eventi per stato', function () use (&$fetchRange) {
    $admin = User::factory()->create()->assignRole('admin');
    $staff = User::factory()->create()->assignRole('staff');

    $confirmed = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(1),
    ]);
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'status'         => 'pending',
        'scheduled_date' => now()->addDays(2),
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('filterStatus', ['confirmed'])
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($confirmed->id);
});

it('filtra gli eventi per cliente', function () use (&$fetchRange) {
    $admin     = User::factory()->create()->assignRole('admin');
    $staff     = User::factory()->create()->assignRole('staff');
    $customer1 = User::factory()->create()->assignRole('customer');
    $customer2 = User::factory()->create()->assignRole('customer');

    $own = Appointment::factory()->create([
        'user_id'        => $customer1->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(1),
    ]);
    Appointment::factory()->create([
        'user_id'        => $customer2->id,
        'staff_id'       => $staff->id,
        'scheduled_date' => now()->addDays(2),
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('filterCustomer', [$customer1->id])
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});

it('filtra gli eventi per servizio', function () use (&$fetchRange) {
    $admin    = User::factory()->create()->assignRole('admin');
    $staff    = User::factory()->create()->assignRole('staff');
    $service1 = Service::factory()->create();
    $service2 = Service::factory()->create();

    $own = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service1->id],
        'scheduled_date' => now()->addDays(1),
    ]);
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_ids'    => [$service2->id],
        'scheduled_date' => now()->addDays(2),
    ]);

    $this->actingAs($admin);

    $events = Livewire::test(AppointmentCalendarWidget::class)
        ->set('filterService', [$service1->id])
        ->instance()
        ->fetchEvents($fetchRange());

    expect($events)->toHaveCount(1)
        ->and($events[0]['id'])->toBe($own->id);
});
```

- [ ] **Step 2: Run tests to confirm failures**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "filtra"
```

Expected: 5 failures — `staffFilter` property not found, `filterStaff/Status/Customer/Service` not found.

- [ ] **Step 3: Update AppointmentCalendarWidget**

Replace properties, listener, and `fetchEvents` query. Full new version of the relevant sections:

```php
// Replace the single property:
// public ?int $staffFilter = null;
// With:
public array $filterStaff = [];
public array $filterStatus = [];
public array $filterService = [];
public array $filterCustomer = [];
```

In `fetchEvents`, replace the staff filter block and add the three new filters:

```php
public function fetchEvents(array $fetchInfo): array
{
    $query = Appointment::query()
        ->with(['user', 'staff'])
        ->whereBetween('scheduled_date', [$fetchInfo['start'], $fetchInfo['end']]);

    $user = auth()->user();

    if ($user->isStaff()) {
        $query->where('staff_id', $user->id);
    } elseif ($user->isAdmin() && !empty($this->filterStaff)) {
        $query->whereIn('staff_id', $this->filterStaff);
    }

    if (!empty($this->filterStatus)) {
        $query->whereIn('status', $this->filterStatus);
    }

    if (!empty($this->filterCustomer)) {
        $query->whereIn('user_id', $this->filterCustomer);
    }

    if (!empty($this->filterService)) {
        $query->whereRaw('JSON_OVERLAPS(service_ids, ?)', [json_encode($this->filterService)]);
    }

    $appointments = $query->get();
    // ... rest of the method unchanged
```

Replace the `handleStaffFilterUpdated` listener:

```php
// Remove:
// #[On('calendar-staff-filter-updated')]
// public function handleStaffFilterUpdated(?int $staffId): void { ... }

// Add:
#[On('calendar-filters-updated')]
public function handleFiltersUpdated(array $staff, array $status, array $service, array $customer): void
{
    $this->filterStaff   = $staff;
    $this->filterStatus  = $status;
    $this->filterService = $service;
    $this->filterCustomer = $customer;
    $this->dispatch('filament-fullcalendar--refresh');
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php --filter "filtra"
```

Expected: 5 passed.

- [ ] **Step 5: Run full suite to catch regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: all pass.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php tests/Feature/Filament/AppointmentCalendarTest.php
git commit -m "feat: multi-select filter state and query in AppointmentCalendarWidget"
```

---

### Task 2: Update page — properties, form schema, dispatch

**Files:**
- Modify: `app/Filament/Pages/AppointmentCalendar.php`

- [ ] **Step 1: Replace AppointmentCalendar page**

Full replacement of the class body (imports already include `Filament\Facades\Filament`, `App\Models\User`, `Filament\Forms\Components\Select`, `Filament\Schemas\Schema`). Add `use App\Models\Service;`.

Replace `public ?int $staffFilter = null;`, `staffFilterForm()`, and `updatedStaffFilter()` with:

```php
use App\Models\Service; // add to imports

// Properties (replace staffFilter):
public array $filterStaff    = [];
public array $filterStatus   = [];
public array $filterService  = [];
public array $filterCustomer = [];

// Form (replace staffFilterForm):
public function filtersForm(Schema $schema): Schema
{
    $user   = Filament::auth()->user();
    $fields = [];

    if ($user->isAdmin()) {
        $fields[] = Select::make('filterStaff')
            ->label('Staff')
            ->options(fn () => User::role('staff')->orderBy('name')->pluck('name', 'id'))
            ->placeholder('Tutti')
            ->multiple()
            ->live();
    }

    $fields[] = Select::make('filterStatus')
        ->label('Stato')
        ->options([
            'pending'   => 'In attesa',
            'confirmed' => 'Confermato',
            'completed' => 'Completato',
            'cancelled' => 'Annullato',
        ])
        ->placeholder('Tutti')
        ->multiple()
        ->live();

    $fields[] = Select::make('filterService')
        ->label('Servizio')
        ->options(fn () => Service::orderBy('name')->pluck('name', 'id'))
        ->placeholder('Tutti')
        ->multiple()
        ->live();

    $fields[] = Select::make('filterCustomer')
        ->label('Cliente')
        ->options(fn () => User::role('customer')->orderBy('name')->pluck('name', 'id'))
        ->placeholder('Tutti')
        ->multiple()
        ->live();

    return $schema->schema($fields)->columns(2);
}

// Dispatch methods (replace updatedStaffFilter):
public function updatedFilterStaff(): void    { $this->dispatchFilters(); }
public function updatedFilterStatus(): void   { $this->dispatchFilters(); }
public function updatedFilterService(): void  { $this->dispatchFilters(); }
public function updatedFilterCustomer(): void { $this->dispatchFilters(); }

private function dispatchFilters(): void
{
    $this->dispatch('calendar-filters-updated',
        staff:    $this->filterStaff,
        status:   $this->filterStatus,
        service:  $this->filterService,
        customer: $this->filterCustomer,
    )->to(AppointmentCalendarWidget::class);
}
```

- [ ] **Step 2: Run full test suite to confirm no regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/AppointmentCalendar.php
git commit -m "feat: multi-select filtersForm and dispatch in AppointmentCalendar"
```

---

### Task 3: Update view — filtersForm for all roles

**Files:**
- Modify: `resources/views/filament/pages/appointment-calendar.blade.php`

- [ ] **Step 1: Replace view content**

```blade
<x-filament-panels::page>
    <div class="mb-4">
        {{ $this->filtersForm }}
    </div>
</x-filament-panels::page>
```

The `@if(auth()->user()->isAdmin())` wrapper is removed — the form schema method itself conditionally includes the staff field.

- [ ] **Step 2: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/pages/appointment-calendar.blade.php
git commit -m "feat: show filtersForm for all roles in calendar view"
```

---

### Task 4: Fix layout — calendar renders below filters

**Files:**
- Modify: `app/Filament/Pages/AppointmentCalendar.php`

- [ ] **Step 1: Change getHeaderWidgets to getFooterWidgets**

In `AppointmentCalendar.php`, rename the method:

```php
// Remove:
// protected function getHeaderWidgets(): array

// Add:
protected function getFooterWidgets(): array
{
    return [AppointmentCalendarWidget::class];
}
```

- [ ] **Step 2: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/AppointmentCalendarTest.php
```

Expected: all pass.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/AppointmentCalendar.php
git commit -m "fix: render calendar below filters using getFooterWidgets"
```
