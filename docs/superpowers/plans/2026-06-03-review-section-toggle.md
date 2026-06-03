# Review Section Visibility Toggle — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a global admin toggle to show or hide the reviews section on the salon storefront, stored in `SystemSetting` and exposed in the Impostazioni Filament page.

**Architecture:** One new `reviews_enabled` boolean column in `system_settings` (migration + model). `BookingController::index()` checks `SystemSetting::isReviewsEnabled()` and passes an empty collection when disabled — the blade already wraps the section in `@if($reviews->isNotEmpty())`. The Filament `SystemSettings` page gets a "Sito web" section with a `Toggle`.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest

---

## File Map

| File | Change |
|------|--------|
| `database/migrations/2026_06_03_000000_add_reviews_enabled_to_system_settings.php` | Create |
| `app/Models/SystemSetting.php` | Add fillable, cast, defaults, `isReviewsEnabled()` helper |
| `app/Services/BusinessProvisioningService.php` | Add `reviews_enabled => true` to `createInfrastructure()` |
| `app/Http/Controllers/Portal/BookingController.php` | Check flag before querying reviews |
| `app/Filament/Pages/SystemSettings.php` | Add "Sito web" section with `Toggle` |
| `tests/Feature/Models/SystemSettingTest.php` | New — unit tests for `isReviewsEnabled()` |
| `tests/Feature/Http/WelcomeTest.php` | Add integration test for disabled reviews |

---

## Task 1: Migration, Model, Service

**Files:**
- Create: `database/migrations/2026_06_03_000000_add_reviews_enabled_to_system_settings.php`
- Modify: `app/Models/SystemSetting.php`
- Modify: `app/Services/BusinessProvisioningService.php`
- Create: `tests/Feature/Models/SystemSettingTest.php`

**Context:** All Feature tests have `app()->instance('current_business_id', 1)` in `beforeEach` (see `tests/Pest.php`). `SystemSetting::current()` uses `firstOrCreate(['business_id' => Business::currentId()], [...defaults...])`.

- [ ] **Step 1: Write failing unit tests**

Create `tests/Feature/Models/SystemSettingTest.php`:

```php
<?php

use App\Models\SystemSetting;

it('isReviewsEnabled returns true by default', function () {
    expect(SystemSetting::isReviewsEnabled())->toBeTrue();
});

it('isReviewsEnabled returns false when disabled', function () {
    SystemSetting::current()->update(['reviews_enabled' => false]);

    expect(SystemSetting::isReviewsEnabled())->toBeFalse();
});

it('isReviewsEnabled returns true when re-enabled', function () {
    SystemSetting::current()->update(['reviews_enabled' => false]);
    SystemSetting::current()->update(['reviews_enabled' => true]);

    expect(SystemSetting::isReviewsEnabled())->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php -v
```

Expected: FAIL — `isReviewsEnabled` method does not exist or column not found.

- [ ] **Step 3: Create migration**

Create `database/migrations/2026_06_03_000000_add_reviews_enabled_to_system_settings.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('reviews_enabled')->default(true)->after('payment_mode');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('reviews_enabled');
        });
    }
};
```

- [ ] **Step 4: Update SystemSetting model**

Replace the full content of `app/Models/SystemSetting.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use App\Models\Business;

#[Fillable([
    'business_id',
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
    'reviews_enabled',
])]
class SystemSetting extends Model
{
    use BelongsToBusiness;

    protected function casts(): array
    {
        return [
            'slot_generation_weeks'       => 'integer',
            'slot_granularity_minutes'    => 'integer',
            'booking_max_days_ahead'      => 'integer',
            'cancellation_deadline_hours' => 'integer',
            'reminder_count'              => 'integer',
            'reminder_1_hours'            => 'integer',
            'reminder_2_hours'            => 'integer',
            'reviews_enabled'             => 'boolean',
        ];
    }

    public static function current(): self
    {
        if (! app()->bound('current_business_id')) {
            return new self([
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 30,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'reminder_2_hours'            => 2,
                'payment_mode'                => 'both',
                'reviews_enabled'             => true,
            ]);
        }

        return self::firstOrCreate(
            ['business_id' => Business::currentId()],
            [
                'slot_generation_weeks'       => 4,
                'slot_granularity_minutes'    => 15,
                'timezone'                    => 'Europe/Rome',
                'booking_max_days_ahead'      => 30,
                'cancellation_deadline_hours' => 24,
                'reminder_count'              => 1,
                'reminder_1_hours'            => 24,
                'reminder_2_hours'            => 2,
                'payment_mode'                => 'both',
                'reviews_enabled'             => true,
            ]
        );
    }

    public static function isReviewsEnabled(): bool
    {
        return (bool) (self::current()->reviews_enabled ?? true);
    }

    public static function getSlotGranularity(): int
    {
        return self::current()->slot_granularity_minutes;
    }

    public static function getTimezone(): string
    {
        return self::current()->timezone ?? 'Europe/Rome';
    }

    public static function getBookingMaxDaysAhead(): int
    {
        return self::current()->booking_max_days_ahead ?? 90;
    }

    public static function getCancellationDeadlineHours(): int
    {
        return self::current()->cancellation_deadline_hours ?? 24;
    }

    public static function getReminderCount(): int
    {
        return self::current()->reminder_count ?? 1;
    }

    public static function getReminder1Hours(): int
    {
        return self::current()->reminder_1_hours ?? 24;
    }

    public static function getReminder2Hours(): int
    {
        return self::current()->reminder_2_hours ?? 2;
    }

    public static function getPaymentMode(): string
    {
        return self::current()->payment_mode ?? 'both';
    }
}
```

- [ ] **Step 5: Update BusinessProvisioningService**

In `app/Services/BusinessProvisioningService.php`, in `createInfrastructure()`, add `reviews_enabled` to `SystemSetting::create(...)`:

```php
SystemSetting::create([
    'business_id'                 => $business->id,
    'slot_generation_weeks'       => 4,
    'slot_granularity_minutes'    => 15,
    'timezone'                    => 'Europe/Rome',
    'booking_max_days_ahead'      => 30,
    'cancellation_deadline_hours' => 24,
    'reminder_count'              => 1,
    'reminder_1_hours'            => 24,
    'reminder_2_hours'            => 2,
    'payment_mode'                => 'both',
    'reviews_enabled'             => true,
]);
```

- [ ] **Step 6: Run migration**

```
docker-compose run --rm app php artisan migrate
```

Expected: `Migrating: 2026_06_03_000000_add_reviews_enabled_to_system_settings` then `Migrated`.

- [ ] **Step 7: Run unit tests to verify they pass**

```
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php -v
```

Expected: 3 tests PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_03_000000_add_reviews_enabled_to_system_settings.php \
        app/Models/SystemSetting.php \
        app/Services/BusinessProvisioningService.php \
        tests/Feature/Models/SystemSettingTest.php
git commit -m "feat: add reviews_enabled to SystemSetting"
```

---

## Task 2: BookingController

**Files:**
- Modify: `app/Http/Controllers/Portal/BookingController.php` (line 42)
- Modify: `tests/Feature/Http/WelcomeTest.php`

**Context:** `index()` currently runs `SalonReview::published()->ordered()->get()` unconditionally. `welcome.blade.php` wraps the reviews section in `@if($reviews->isNotEmpty())` — passing `collect()` is enough to suppress the section.

- [ ] **Step 1: Write failing integration test**

In `tests/Feature/Http/WelcomeTest.php`, add at the end. Existing imports: `SalonReview`, `SalonProfile`, `User`, `Role`. Add `SystemSetting` to the use statements at the top of the file:

```php
use App\Models\SystemSetting;
```

Then append the test:

```php
it('homepage passes empty reviews when reviews section is disabled', function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

    SalonReview::factory()->create(['is_published' => true]);
    SystemSetting::current()->update(['reviews_enabled' => false]);

    $response = $this->get('/');

    $response->assertViewHas('reviews', fn ($reviews) => $reviews->isEmpty());
});
```

- [ ] **Step 2: Run test to verify it fails**

```
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/WelcomeTest.php --filter "reviews section is disabled" -v
```

Expected: FAIL — the controller still returns published reviews regardless of the flag.

- [ ] **Step 3: Update BookingController**

In `app/Http/Controllers/Portal/BookingController.php`, replace line 42:

```php
$reviews = SalonReview::published()->ordered()->get();
```

With:

```php
$reviews = SystemSetting::isReviewsEnabled()
    ? SalonReview::published()->ordered()->get()
    : collect();
```

- [ ] **Step 4: Run all WelcomeTest tests**

```
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Http/WelcomeTest.php -v
```

Expected: all 4 tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Portal/BookingController.php \
        tests/Feature/Http/WelcomeTest.php
git commit -m "feat: respect reviews_enabled flag in BookingController"
```

---

## Task 3: SystemSettings Filament Page

**Files:**
- Modify: `app/Filament/Pages/SystemSettings.php`

**Context:** The page has a `mount()` method that pre-fills the Filament form from `SystemSetting::current()`, and a `save()` method that calls `SystemSetting::current()->update($this->form->getState())`. Any field added to the schema and included in `mount()` will be automatically saved by the existing `save()` — no changes to `save()` are needed.

Current imports in the file: `Select`, `TextInput`, `Section`, `Get`, `Notification`, `SystemSetting`, `Page`, `Schema`. Need to add `Toggle`.

- [ ] **Step 1: Add Toggle import**

In `app/Filament/Pages/SystemSettings.php`, add after the `use Filament\Forms\Components\Select;` line:

```php
use Filament\Forms\Components\Toggle;
```

- [ ] **Step 2: Add reviews_enabled to mount()**

In `mount()`, add `reviews_enabled` to the `$this->form->fill([...])` array:

```php
public function mount(): void
{
    $setting = SystemSetting::current();
    $this->form->fill([
        'slot_granularity_minutes'    => $setting->slot_granularity_minutes,
        'booking_max_days_ahead'      => $setting->booking_max_days_ahead,
        'cancellation_deadline_hours' => $setting->cancellation_deadline_hours,
        'reminder_count'              => (string) $setting->reminder_count,
        'reminder_1_hours'            => $setting->reminder_1_hours,
        'reminder_2_hours'            => $setting->reminder_2_hours,
        'payment_mode'                => $setting->payment_mode ?? 'both',
        'reviews_enabled'             => $setting->reviews_enabled ?? true,
    ]);
}
```

- [ ] **Step 3: Add "Sito web" section to the form schema**

In `form()`, after the `Section::make('Pagamenti')` closing parenthesis and before `->statePath('data')`, add:

```php
Section::make('Sito web')
    ->schema([
        Toggle::make('reviews_enabled')
            ->label('Mostra sezione recensioni')
            ->helperText('Se disattivato, la sezione recensioni non compare sul sito del salone')
            ->default(true),
    ]),
```

- [ ] **Step 4: Run the full test suite**

```
docker-compose run --rm app ./vendor/bin/pest -v
```

Expected: all tests PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php
git commit -m "feat: add reviews toggle to SystemSettings admin page"
```
