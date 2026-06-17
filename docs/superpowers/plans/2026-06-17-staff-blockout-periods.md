# Staff Blockout Periods Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add date-range blockout periods to staff availability so that admins can block a collaborator's bookings during vacations or absences.

**Architecture:** A new `staff_blockouts` table stores date ranges per staff member. `SlotCalculationService::getWorkRanges()` checks for an active blockout before querying `AvailabilityRule` — if a blockout covers the requested date, it returns `[]` immediately. The admin UI lives in the existing ManageAvailability Filament page as a separate Livewire section outside the weekly form.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Livewire v3, MySQL, Pest

---

## File Map

| File | Action |
|------|--------|
| `database/migrations/2026_06_17_100000_create_staff_blockouts_table.php` | Create |
| `app/Models/StaffBlockout.php` | Create |
| `database/factories/StaffBlockoutFactory.php` | Create |
| `app/Services/Booking/SlotCalculationService.php` | Modify — add blockout check in `getWorkRanges()` |
| `tests/Feature/Services/SlotCalculationServiceTest.php` | Modify — add 2 blockout tests |
| `app/Filament/Resources/StaffResource/Pages/ManageAvailability.php` | Modify — add public properties + 3 Livewire methods |
| `resources/views/filament/resources/staff-resource/pages/manage-availability.blade.php` | Modify — add "Periodi di assenza" section |

---

### Task 1: Migration, Model, Factory

**Files:**
- Create: `database/migrations/2026_06_17_100000_create_staff_blockouts_table.php`
- Create: `app/Models/StaffBlockout.php`
- Create: `database/factories/StaffBlockoutFactory.php`

- [ ] **Step 1: Create migration**

Create `database/migrations/2026_06_17_100000_create_staff_blockouts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_blockouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_blockouts');
    }
};
```

- [ ] **Step 2: Create model**

Create `app/Models/StaffBlockout.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'user_id', 'start_date', 'end_date', 'reason'])]
class StaffBlockout extends Model
{
    /** @use HasFactory<\Database\Factories\StaffBlockoutFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date'   => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 3: Create factory**

Create `database/factories/StaffBlockoutFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\StaffBlockout;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffBlockout> */
class StaffBlockoutFactory extends Factory
{
    protected $model = StaffBlockout::class;

    public function definition(): array
    {
        return [
            'business_id' => 1,
            'user_id'     => User::factory(),
            'start_date'  => '2026-07-14',
            'end_date'    => '2026-07-18',
            'reason'      => null,
        ];
    }
}
```

- [ ] **Step 4: Run migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `staff_blockouts` table created, no errors.

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_17_100000_create_staff_blockouts_table.php \
        app/Models/StaffBlockout.php \
        database/factories/StaffBlockoutFactory.php
git commit -m "feat: add StaffBlockout model and migration"
```

---

### Task 2: Blockout check in SlotCalculationService + tests

**Files:**
- Modify: `app/Services/Booking/SlotCalculationService.php`
- Modify: `tests/Feature/Services/SlotCalculationServiceTest.php`

- [ ] **Step 1: Write two failing tests**

Append to `tests/Feature/Services/SlotCalculationServiceTest.php` (before the last closing line, after existing tests):

```php
// ─── blockout periods ────────────────────────────────────────────────────────

it('returns no slots when staff has an active blockout covering the date', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    $date = Carbon::parse('2026-07-14'); // Monday
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '17:00:00',
        'is_available' => true,
    ]);

    \App\Models\StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-07-14',
        'end_date'   => '2026-07-18',
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    expect($slots)->toBeEmpty();
});

it('returns slots normally on dates outside the blockout period', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $service = Service::factory()->create(['duration_minutes' => 60, 'active' => true]);
    $staff->services()->attach($service->id);

    $date = Carbon::parse('2026-07-21'); // Monday after blockout 14-18
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => $date->dayOfWeek,
        'start_time'   => '09:00:00',
        'end_time'     => '12:00:00',
        'is_available' => true,
    ]);

    \App\Models\StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-07-14',
        'end_date'   => '2026-07-18',
    ]);

    $svc   = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => $date,
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    expect($slots)->not->toBeEmpty();
});
```

- [ ] **Step 2: Run tests to confirm they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/SlotCalculationServiceTest.php --filter "blockout" 2>&1 | tail -10
```

Expected: 2 tests FAIL (StaffBlockout class not used yet in service).

- [ ] **Step 3: Add blockout check to SlotCalculationService**

In `app/Services/Booking/SlotCalculationService.php`, add the `StaffBlockout` import at the top with the other `use` statements:

```php
use App\Models\StaffBlockout;
```

Then in the `getWorkRanges()` method, add the blockout check as the **first thing** in the method, before querying `AvailabilityRule`:

```php
private function getWorkRanges(User $staff, Carbon $date): array
{
    $hasBlockout = StaffBlockout::where('user_id', $staff->id)
        ->where('start_date', '<=', $date->toDateString())
        ->where('end_date', '>=', $date->toDateString())
        ->exists();

    if ($hasBlockout) {
        return [];
    }

    $rules = AvailabilityRule::where('user_id', $staff->id)
        ->where('day_of_week', $date->dayOfWeek)
        ->where('is_available', true)
        ->get();

    $ranges = [];
    foreach ($rules as $rule) {
        $ranges[] = [
            'start' => $date->copy()->setTimeFromTimeString($rule->start_time),
            'end'   => $date->copy()->setTimeFromTimeString($rule->end_time),
        ];
        if ($rule->start_time_2 && $rule->end_time_2) {
            $ranges[] = [
                'start' => $date->copy()->setTimeFromTimeString($rule->start_time_2),
                'end'   => $date->copy()->setTimeFromTimeString($rule->end_time_2),
            ];
        }
    }

    return $ranges;
}
```

- [ ] **Step 4: Run tests to confirm they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/SlotCalculationServiceTest.php --filter "blockout" 2>&1 | tail -10
```

Expected: 2 tests PASS.

- [ ] **Step 5: Run full SlotCalculationService test suite to check no regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/SlotCalculationServiceTest.php 2>&1 | tail -5
```

Expected: all existing tests still pass.

- [ ] **Step 6: Commit**

```bash
git add app/Services/Booking/SlotCalculationService.php \
        tests/Feature/Services/SlotCalculationServiceTest.php
git commit -m "feat: block slots when staff has active blockout period"
```

---

### Task 3: ManageAvailability page — Livewire methods

**Files:**
- Modify: `app/Filament/Resources/StaffResource/Pages/ManageAvailability.php`

The current file has: `use` statements, class with `$record`, `$data`, `mount()`, `form()`, `save()`, `getTitle()`.

- [ ] **Step 1: Add import and public properties**

Add `use App\Models\StaffBlockout;` to the existing `use` block at the top of the file, and add four public properties to the class body (after `public ?array $data = [];`):

```php
use App\Models\StaffBlockout;
```

```php
public array $blockouts = [];
public ?string $newStart = null;
public ?string $newEnd = null;
public ?string $newReason = null;
```

- [ ] **Step 2: Call loadBlockouts() from mount()**

At the end of the existing `mount()` method, after `$this->form->fill(['days' => $days]);`, add:

```php
$this->loadBlockouts();
```

- [ ] **Step 3: Add loadBlockouts(), addBlockout(), deleteBlockout() methods**

Add these three methods to the class (after the `save()` method):

```php
public function loadBlockouts(): void
{
    $this->blockouts = StaffBlockout::where('user_id', $this->record->id)
        ->where('end_date', '>=', now()->toDateString())
        ->orderBy('start_date')
        ->get()
        ->map(fn ($b) => [
            'id'         => $b->id,
            'start_date' => $b->start_date->format('d/m/Y'),
            'end_date'   => $b->end_date->format('d/m/Y'),
            'reason'     => $b->reason,
        ])
        ->toArray();
}

public function addBlockout(): void
{
    $this->validate([
        'newStart'  => 'required|date',
        'newEnd'    => 'required|date|after_or_equal:newStart',
        'newReason' => 'nullable|string|max:255',
    ]);

    StaffBlockout::create([
        'user_id'    => $this->record->id,
        'start_date' => $this->newStart,
        'end_date'   => $this->newEnd,
        'reason'     => $this->newReason ?: null,
    ]);

    $this->newStart  = null;
    $this->newEnd    = null;
    $this->newReason = null;

    $this->loadBlockouts();

    Notification::make()->title('Periodo aggiunto')->success()->send();
}

public function deleteBlockout(int $id): void
{
    StaffBlockout::where('user_id', $this->record->id)->where('id', $id)->delete();
    $this->loadBlockouts();

    Notification::make()->title('Periodo rimosso')->success()->send();
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Resources/StaffResource/Pages/ManageAvailability.php
git commit -m "feat: add blockout management methods to ManageAvailability page"
```

---

### Task 4: ManageAvailability blade — "Periodi di assenza" section

**Files:**
- Modify: `resources/views/filament/resources/staff-resource/pages/manage-availability.blade.php`

Current file content:
```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button type="submit">
                Salva disponibilità
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
```

- [ ] **Step 1: Add the "Periodi di assenza" section**

Replace the entire file with:

```blade
<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div class="flex justify-end mt-6">
            <x-filament::button type="submit">
                Salva disponibilità
            </x-filament::button>
        </div>
    </form>

    <div class="mt-10">
        <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Periodi di assenza</h3>

        @if(count($this->blockouts))
        <div class="mb-4 rounded-xl border border-gray-200 dark:border-gray-700 divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
            @foreach($this->blockouts as $blockout)
            <div class="flex items-center justify-between px-4 py-3">
                <div>
                    <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $blockout['start_date'] }} — {{ $blockout['end_date'] }}</span>
                    @if($blockout['reason'])
                    <span class="ml-2 text-sm text-gray-500 dark:text-gray-400">· {{ $blockout['reason'] }}</span>
                    @endif
                </div>
                <button wire:click="deleteBlockout({{ $blockout['id'] }})"
                    wire:confirm="Eliminare questo periodo di assenza?"
                    class="text-sm text-red-600 dark:text-red-400 hover:underline ml-4">
                    Elimina
                </button>
            </div>
            @endforeach
        </div>
        @else
        <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Nessun periodo di assenza impostato.</p>
        @endif

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">Aggiungi periodo</p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Dal</label>
                    <input type="date" wire:model="newStart"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('newStart') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Al</label>
                    <input type="date" wire:model="newEnd"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                    @error('newEnd') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Motivo (opzionale)</label>
                    <input type="text" wire:model="newReason" placeholder="es. Ferie agosto"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-primary-500">
                </div>
            </div>
            <div class="mt-3">
                <x-filament::button wire:click="addBlockout" size="sm">
                    Aggiungi periodo
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 2: Run migration on test DB and verify no test regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app php artisan migrate
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/SlotCalculationServiceTest.php 2>&1 | tail -5
```

Expected: all tests pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/filament/resources/staff-resource/pages/manage-availability.blade.php
git commit -m "feat: add periodi di assenza UI to ManageAvailability page"
```
