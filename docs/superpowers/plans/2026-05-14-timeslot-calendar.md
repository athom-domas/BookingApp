# TimeSlot Calendar View — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the flat TimeSlots table with a weekly calendar view per staff member, accessible from the sidebar as "Slot".

**Architecture:** A single custom Filament page (`TimeSlotCalendar`) acts as a Livewire component holding `$staffId` and `$weekStart` state. It queries `TimeSlot` records for the selected staff and week, groups them by date via `#[Computed]` properties, and renders a 7-column Tailwind CSS grid. No new models or migrations needed.

**Tech Stack:** Laravel 13, Filament 4, Livewire 3, Tailwind CSS, Pest

---

## File Map

| Action | Path | Responsibility |
|--------|------|----------------|
| Create | `app/Filament/Pages/TimeSlotCalendar.php` | Livewire page: state, queries, week navigation |
| Create | `resources/views/filament/pages/time-slot-calendar.blade.php` | Calendar grid blade view |
| Create | `tests/Feature/Filament/Pages/TimeSlotCalendarTest.php` | Page behaviour tests |

Existing files unchanged:
- `app/Filament/Resources/TimeSlotResource.php` — already hidden from nav (`shouldRegisterNavigation(): false`), no modifications needed
- `app/Models/TimeSlot.php` — no changes

---

### Task 1: Write failing tests

**Files:**
- Create: `tests/Feature/Filament/Pages/TimeSlotCalendarTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Filament\Pages\TimeSlotCalendar;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->staff = User::factory()->create();
    $this->staff->assignRole('staff');
});

it('renders the calendar page', function () {
    $this->get('/admin/time-slot-calendar')->assertOk();
});

it('defaults to the current week start (Monday)', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSet('weekStart', now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d'));
});

it('navigates to the previous week', function () {
    $expected = now()->startOfWeek(Carbon::MONDAY)->subWeek()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('previousWeek')
        ->assertSet('weekStart', $expected);
});

it('navigates to the next week', function () {
    $expected = now()->startOfWeek(Carbon::MONDAY)->addWeek()->format('Y-m-d');

    livewire(TimeSlotCalendar::class)
        ->call('nextWeek')
        ->assertSet('weekStart', $expected);
});

it('shows prompt when no staff is selected', function () {
    livewire(TimeSlotCalendar::class)
        ->assertSeeHtml('Seleziona uno staff');
});

it('loads slots for the selected staff and current week', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => $monday->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSee('09:00')
        ->assertSee('09:30');
});

it('marks available slots with green class', function () {
    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'        => $this->staff->id,
        'date'           => $monday->format('Y-m-d'),
        'start_time'     => '09:00:00',
        'end_time'       => '09:30:00',
        'is_available'   => true,
        'appointment_id' => null,
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertSeeHtml('bg-green-100');
});

it('does not load slots belonging to other staff', function () {
    $other = User::factory()->create();
    $other->assignRole('staff');

    $monday = now()->startOfWeek(Carbon::MONDAY);

    TimeSlot::factory()->create([
        'user_id'    => $other->id,
        'date'       => $monday->format('Y-m-d'),
        'start_time' => '14:00:00',
        'end_time'   => '14:30:00',
    ]);

    livewire(TimeSlotCalendar::class)
        ->set('staffId', $this->staff->id)
        ->assertDontSee('14:00');
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/TimeSlotCalendarTest.php
```

Expected: all FAILs — `TimeSlotCalendar` class not found.

---

### Task 2: Create the TimeSlotCalendar page

**Files:**
- Create: `app/Filament/Pages/TimeSlotCalendar.php`

- [ ] **Step 1: Create the page class**

```php
<?php

namespace App\Filament\Pages;

use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

class TimeSlotCalendar extends Page
{
    protected static string $view = 'filament.pages.time-slot-calendar';

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Slot';

    protected static ?string $title = 'Calendario Slot';

    protected static ?int $navigationSort = 4;

    public ?int $staffId = null;

    public string $weekStart;

    public function mount(): void
    {
        $this->weekStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }

    public function previousWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->subWeek()->format('Y-m-d');
    }

    public function nextWeek(): void
    {
        $this->weekStart = Carbon::parse($this->weekStart)->addWeek()->format('Y-m-d');
    }

    #[Computed]
    public function weekDays(): array
    {
        $start = Carbon::parse($this->weekStart);

        return array_map(fn (int $i) => $start->copy()->addDays($i), range(0, 6));
    }

    #[Computed]
    public function slots(): Collection
    {
        if (! $this->staffId) {
            return collect();
        }

        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->addDays(6);

        return TimeSlot::where('user_id', $this->staffId)
            ->whereBetween('date', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeSlot $slot) => Carbon::parse($slot->date)->format('Y-m-d'));
    }

    #[Computed]
    public function staffOptions(): array
    {
        return User::role('staff')->orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function getWeekLabel(): string
    {
        $start = Carbon::parse($this->weekStart);
        $end = $start->copy()->addDays(6);

        return $start->format('d/m') . ' – ' . $end->format('d/m/Y');
    }
}
```

- [ ] **Step 2: Run tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/TimeSlotCalendarTest.php
```

Expected: FAILs shift — page class found, but view template missing.

---

### Task 3: Create the blade view

**Files:**
- Create: `resources/views/filament/pages/time-slot-calendar.blade.php`

- [ ] **Step 1: Create the directory**

```bash
docker-compose run --rm --no-deps app mkdir -p resources/views/filament/pages
```

- [ ] **Step 2: Create the blade template**

```blade
<x-filament-panels::page>
    <div class="space-y-4">

        {{-- Controls --}}
        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:model.live="staffId"
                class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm text-sm focus:ring-primary-500 focus:border-primary-500 py-1.5 px-3"
            >
                <option value="">Seleziona staff...</option>
                @foreach ($this->staffOptions as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-1">
                <button
                    wire:click="previousWeek"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400"
                    title="Settimana precedente"
                >
                    <x-heroicon-o-chevron-left class="w-5 h-5" />
                </button>

                <span class="text-sm font-medium min-w-[150px] text-center text-gray-700 dark:text-gray-300">
                    {{ $this->getWeekLabel() }}
                </span>

                <button
                    wire:click="nextWeek"
                    class="p-1 rounded hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400"
                    title="Settimana successiva"
                >
                    <x-heroicon-o-chevron-right class="w-5 h-5" />
                </button>
            </div>
        </div>

        @if (! $staffId)
            <div class="flex items-center justify-center py-16 text-gray-400 dark:text-gray-500 text-sm">
                Seleziona uno staff per vedere il calendario.
            </div>
        @else
            {{-- Calendar grid --}}
            <div class="grid grid-cols-7 gap-2">
                @foreach ($this->weekDays as $day)
                    @php
                        $key = $day->format('Y-m-d');
                        $daySlots = $this->slots->get($key, collect());
                    @endphp
                    <div class="min-h-[120px] rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-2 space-y-1">
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 capitalize">
                            {{ $day->isoFormat('ddd D MMM') }}
                        </div>

                        @forelse ($daySlots as $slot)
                            @php
                                $available = $slot->is_available && is_null($slot->appointment_id);
                            @endphp
                            <div class="rounded px-1.5 py-0.5 text-xs font-mono leading-tight
                                {{ $available
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' }}">
                                {{ substr($slot->start_time, 0, 5) }}–{{ substr($slot->end_time, 0, 5) }}
                            </div>
                        @empty
                            <span class="text-gray-300 dark:text-gray-600 text-sm">—</span>
                        @endforelse
                    </div>
                @endforeach
            </div>
        @endif

    </div>
</x-filament-panels::page>
```

- [ ] **Step 3: Run the feature tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/Pages/TimeSlotCalendarTest.php
```

Expected: all PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/TimeSlotCalendar.php \
        resources/views/filament/pages/time-slot-calendar.blade.php \
        tests/Feature/Filament/Pages/TimeSlotCalendarTest.php
git commit -m "feat: add TimeSlotCalendar weekly grid page"
```

---

### Task 4: Full suite and browser verification

- [ ] **Step 1: Run the full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all green, no regressions.

- [ ] **Step 2: Verify in the browser**

Open `http://localhost/admin/time-slot-calendar`. Check:
- "Slot" entry appears in the sidebar
- Selecting a staff member renders the 7-column weekly grid
- Prev/next buttons shift the week correctly
- Available slots appear with a green badge, occupied with red
- Days with no slots show "—"
- Dark mode renders correctly if enabled

- [ ] **Step 3: Commit if any tweaks were needed**

```bash
git add -p
git commit -m "fix: adjust TimeSlotCalendar layout tweaks"
```
