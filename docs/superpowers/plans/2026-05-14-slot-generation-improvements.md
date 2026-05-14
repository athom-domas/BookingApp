# Slot Generation Improvements Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the slot generation horizon configurable from the admin panel and give admins a confirmation dialog to regenerate future slots when a staff member's slot duration changes.

**Architecture:** A singleton `SystemSetting` model holds global settings (starting with `slot_generation_weeks`). `GenerateWeeklySlots` reads this value and iterates over multiple weeks. A new `RegenerateStaffSlots` job handles on-demand slot rebuilding. `EditStaff` detects duration changes and mounts a confirmation modal.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest, Queue (sync in tests)

---

## File Map

| Action | File |
|--------|------|
| Create | `database/migrations/2026_05_14_200000_create_system_settings_table.php` |
| Create | `database/seeders/SystemSettingSeeder.php` |
| Modify | `database/seeders/DatabaseSeeder.php` |
| Create | `app/Models/SystemSetting.php` |
| Create | `app/Filament/Pages/SystemSettings.php` |
| Create | `resources/views/filament/pages/system-settings.blade.php` |
| Modify | `app/Jobs/GenerateWeeklySlots.php` |
| Create | `app/Jobs/RegenerateStaffSlots.php` |
| Modify | `app/Filament/Resources/StaffResource/Pages/EditStaff.php` |
| Create | `tests/Feature/Models/SystemSettingTest.php` |
| Create | `tests/Feature/Filament/SystemSettingsPageTest.php` |
| Modify | `tests/Feature/Jobs/GenerateWeeklySlotsTest.php` |
| Create | `tests/Feature/Jobs/RegenerateStaffSlotsTest.php` |
| Modify | `tests/Feature/Filament/StaffResourceTest.php` |

---

## Task 1: SystemSetting model, migration, seeder

**Files:**
- Create: `database/migrations/2026_05_14_200000_create_system_settings_table.php`
- Create: `database/seeders/SystemSettingSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Create: `app/Models/SystemSetting.php`
- Create: `tests/Feature/Models/SystemSettingTest.php`

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/Models/SystemSettingTest.php
<?php

use App\Models\SystemSetting;

it('creates a default row with slot_generation_weeks = 4 when none exists', function () {
    expect(SystemSetting::count())->toBe(0);

    $setting = SystemSetting::current();

    expect(SystemSetting::count())->toBe(1);
    expect($setting->slot_generation_weeks)->toBe(4);
});

it('returns the existing row without creating a new one on repeated calls', function () {
    SystemSetting::current();
    SystemSetting::current();

    expect(SystemSetting::count())->toBe(1);
});

it('always returns the row with id = 1', function () {
    $setting = SystemSetting::current();

    expect($setting->id)->toBe(1);
});

it('casts slot_generation_weeks to integer', function () {
    $setting = SystemSetting::current();
    $setting->update(['slot_generation_weeks' => '8']);

    expect(SystemSetting::current()->slot_generation_weeks)->toBe(8);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php
```

Expected: FAIL — class `SystemSetting` not found.

- [ ] **Step 3: Create migration**

```php
// database/migrations/2026_05_14_200000_create_system_settings_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('slot_generation_weeks')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
```

- [ ] **Step 4: Create model**

```php
// app/Models/SystemSetting.php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['slot_generation_weeks'])]
class SystemSetting extends Model
{
    protected function casts(): array
    {
        return ['slot_generation_weeks' => 'integer'];
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], ['slot_generation_weeks' => 4]);
    }
}
```

- [ ] **Step 5: Create seeder**

```php
// database/seeders/SystemSettingSeeder.php
<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    public function run(): void
    {
        SystemSetting::firstOrCreate(['id' => 1], ['slot_generation_weeks' => 4]);
    }
}
```

- [ ] **Step 6: Add seeder to DatabaseSeeder**

```php
// database/seeders/DatabaseSeeder.php — updated run()
public function run(): void
{
    $this->call(RolesAndUsersSeeder::class);
    $this->call(SystemSettingSeeder::class);
    $this->call(DemoBookingSeeder::class);
}
```

- [ ] **Step 7: Run migration**

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 8: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/SystemSettingTest.php
```

Expected: 4 passing.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_05_14_200000_create_system_settings_table.php \
        database/seeders/SystemSettingSeeder.php \
        database/seeders/DatabaseSeeder.php \
        app/Models/SystemSetting.php \
        tests/Feature/Models/SystemSettingTest.php
git commit -m "feat: add SystemSetting singleton model with slot_generation_weeks"
```

---

## Task 2: Filament SystemSettings page

**Files:**
- Create: `app/Filament/Pages/SystemSettings.php`
- Create: `resources/views/filament/pages/system-settings.blade.php`
- Create: `tests/Feature/Filament/SystemSettingsPageTest.php`

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/Filament/SystemSettingsPageTest.php
<?php

use App\Filament\Pages\SystemSettings;
use App\Models\SystemSetting;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

it('admin can view the system settings page', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSuccessful();
});

it('form is pre-filled with current slot_generation_weeks', function () {
    SystemSetting::firstOrCreate(['id' => 1], ['slot_generation_weeks' => 6]);

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->assertSet('data.slot_generation_weeks', 6);
});

it('admin can update slot_generation_weeks', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 8)
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SystemSetting::current()->slot_generation_weeks)->toBe(8);
});

it('rejects slot_generation_weeks below 1', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 0)
        ->call('save')
        ->assertHasFormErrors(['slot_generation_weeks']);
});

it('rejects slot_generation_weeks above 52', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(SystemSettings::class)
        ->set('data.slot_generation_weeks', 53)
        ->call('save')
        ->assertHasFormErrors(['slot_generation_weeks']);
});

it('non-admin cannot access the system settings page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $this->actingAs($staff);

    expect(SystemSettings::canAccess())->toBeFalse();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/SystemSettingsPageTest.php
```

Expected: FAIL — class `SystemSettings` not found.

- [ ] **Step 3: Create Filament page class**

```php
// app/Filament/Pages/SystemSettings.php
<?php

namespace App\Filament\Pages;

use App\Models\SystemSetting;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SystemSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $view = 'filament.pages.system-settings';

    protected static ?string $navigationLabel = 'Impostazioni';

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'slot_generation_weeks' => SystemSetting::current()->slot_generation_weeks,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('slot_generation_weeks')
                    ->label('Settimane di anticipo per la generazione degli slot')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(52)
                    ->required()
                    ->suffix('settimane'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        SystemSetting::current()->update($this->form->getState());

        Notification::make()
            ->title('Impostazioni salvate')
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }
}
```

- [ ] **Step 4: Create Blade view**

```blade
{{-- resources/views/filament/pages/system-settings.blade.php --}}
<x-filament-panels::page>
    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit">
            Salva
        </x-filament::button>
    </x-filament-panels::form>
</x-filament-panels::page>
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/SystemSettingsPageTest.php
```

Expected: 6 passing.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php \
        resources/views/filament/pages/system-settings.blade.php \
        tests/Feature/Filament/SystemSettingsPageTest.php
git commit -m "feat: add Filament SystemSettings page for slot generation horizon"
```

---

## Task 3: GenerateWeeklySlots — respect configured horizon

**Files:**
- Modify: `app/Jobs/GenerateWeeklySlots.php`
- Modify: `tests/Feature/Jobs/GenerateWeeklySlotsTest.php`

- [ ] **Step 1: Add `beforeEach` to existing test file and update assertions**

Replace the full content of `tests/Feature/Jobs/GenerateWeeklySlotsTest.php`:

```php
<?php

use App\Jobs\GenerateWeeklySlots;
use App\Models\AvailabilityRule;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Mockery;

beforeEach(function () {
    SystemSetting::firstOrCreate(['id' => 1], ['slot_generation_weeks' => 1]);
});

it('GenerateWeeklySlots calls generator for each staff with availability rules', function () {
    $staff1 = User::factory()->create();
    $staff2 = User::factory()->create();
    User::factory()->create(); // user with no availability rules

    AvailabilityRule::factory()->create(['user_id' => $staff1->id, 'is_available' => true]);
    AvailabilityRule::factory()->create(['user_id' => $staff2->id, 'is_available' => true]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->twice()
        ->with(Mockery::type('int'), Mockery::on(fn ($d) => $d instanceof Carbon), Mockery::type('int'))
        ->andReturn(8);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots targets the next Monday week', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    Carbon::setTestNow('2026-05-10 00:00:00'); // Sunday
    $expected = '2026-05-11'; // the Monday immediately after

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::on(fn (Carbon $d) =>
            $d->format('Y-m-d') === $expected
        ), Mockery::type('int'))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);

    Carbon::setTestNow();
});

it('GenerateWeeklySlots passes slot_duration_minutes from staff preferences', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);
    UserPreference::factory()->create(['user_id' => $staff->id, 'slot_duration_minutes' => 15]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::type(Carbon::class), 15)
        ->andReturn(4);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots defaults to 60 minutes when no preferences exist', function () {
    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->once()
        ->with($staff->id, Mockery::type(Carbon::class), 60)
        ->andReturn(8);

    (new GenerateWeeklySlots())->handle($mockGenerator);
});

it('GenerateWeeklySlots generates slots for each week up to the configured horizon', function () {
    SystemSetting::updateOrCreate(['id' => 1], ['slot_generation_weeks' => 3]);

    $staff = User::factory()->create();
    AvailabilityRule::factory()->create(['user_id' => $staff->id, 'is_available' => true]);

    Carbon::setTestNow('2026-05-10 00:00:00'); // Sunday

    $capturedWeeks = [];
    $mockGenerator = $this->mock(SlotGeneratorService::class);
    $mockGenerator->shouldReceive('generateWeeklySlots')
        ->times(3)
        ->with($staff->id, Mockery::on(function (Carbon $d) use (&$capturedWeeks) {
            $capturedWeeks[] = $d->format('Y-m-d');
            return true;
        }), Mockery::type('int'))
        ->andReturn(5);

    (new GenerateWeeklySlots())->handle($mockGenerator);

    expect($capturedWeeks)->toBe(['2026-05-11', '2026-05-18', '2026-05-25']);

    Carbon::setTestNow();
});

it('GenerateWeeklySlots failed hook logs the error', function () {
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('GenerateWeeklySlots failed', \Mockery::on(fn ($ctx) =>
            isset($ctx['error'])
        ));

    $job = new GenerateWeeklySlots();
    $job->failed(new \Exception('DB error'));
});
```

- [ ] **Step 2: Run tests to verify they fail (multi-week test)**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/GenerateWeeklySlotsTest.php
```

Expected: "generates slots for each week up to the configured horizon" FAILS (job still generates 1 week), rest PASS.

- [ ] **Step 3: Update GenerateWeeklySlots job**

```php
// app/Jobs/GenerateWeeklySlots.php
<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateWeeklySlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SlotGeneratorService $generator): void
    {
        $horizon = SystemSetting::current()->slot_generation_weeks;
        $nextWeek = Carbon::now()->startOfWeek(Carbon::MONDAY)->addWeek();

        User::whereHas('availabilityRules', fn ($q) => $q->where('is_available', true))
            ->with('preferences')
            ->each(function (User $staff) use ($generator, $horizon, $nextWeek): void {
                $slotMinutes = $staff->preferences->slot_duration_minutes ?? 60;
                for ($i = 0; $i < $horizon; $i++) {
                    $generator->generateWeeklySlots(
                        $staff->id,
                        $nextWeek->copy()->addWeeks($i),
                        $slotMinutes,
                    );
                }
            });
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateWeeklySlots failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Run all job tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/GenerateWeeklySlotsTest.php
```

Expected: 6 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/GenerateWeeklySlots.php \
        tests/Feature/Jobs/GenerateWeeklySlotsTest.php
git commit -m "feat: GenerateWeeklySlots respects SystemSetting slot_generation_weeks horizon"
```

---

## Task 4: RegenerateStaffSlots job

**Files:**
- Create: `app/Jobs/RegenerateStaffSlots.php`
- Create: `tests/Feature/Jobs/RegenerateStaffSlotsTest.php`

- [ ] **Step 1: Write failing tests**

```php
// tests/Feature/Jobs/RegenerateStaffSlotsTest.php
<?php

use App\Jobs\RegenerateStaffSlots;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\SystemSetting;
use App\Models\TimeSlot;
use App\Models\User;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    SystemSetting::firstOrCreate(['id' => 1], ['slot_generation_weeks' => 1]);
});

it('deletes future unbooked slots for the staff member', function () {
    $staff = User::factory()->create();

    $futureSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($futureSlot->id))->toBeNull();
});

it('preserves booked future slots (appointment_id not null)', function () {
    $staff = User::factory()->create();
    $customer = User::factory()->create();
    $customer->assignRole('customer');

    $service = \App\Models\Service::factory()->create(['duration_minutes' => 60]);
    $appt = Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'user_id'        => $customer->id,
        'service_id'     => $service->id,
        'scheduled_date' => Carbon::today()->addDay(),
        'status'         => 'confirmed',
    ]);

    $bookedSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => $appt->id,
        'is_available'   => false,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($bookedSlot->id))->not->toBeNull();
});

it('preserves past slots regardless of booking status', function () {
    $staff = User::factory()->create();

    $pastSlot = TimeSlot::factory()->create([
        'user_id'        => $staff->id,
        'date'           => Carbon::yesterday(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($pastSlot->id))->not->toBeNull();
});

it('regenerates future slots using the new slot duration', function () {
    Carbon::setTestNow('2026-05-14 10:00:00'); // Wednesday

    $staff = User::factory()->create();
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => Carbon::WEDNESDAY,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '11:00:00',
    ]);

    (new RegenerateStaffSlots($staff->id, 30))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    $slots = TimeSlot::where('user_id', $staff->id)
        ->whereDate('date', '2026-05-14')
        ->orderBy('start_time')
        ->get();

    expect($slots)->toHaveCount(4); // 09:00, 09:30, 10:00, 10:30 with 30-min slots

    Carbon::setTestNow();
});

it('generates slots for each week up to the configured horizon', function () {
    SystemSetting::updateOrCreate(['id' => 1], ['slot_generation_weeks' => 2]);

    Carbon::setTestNow('2026-05-14 10:00:00'); // Wednesday

    $staff = User::factory()->create();
    // Available on Wednesdays
    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => Carbon::WEDNESDAY,
        'is_available' => true,
        'start_time'   => '09:00:00',
        'end_time'     => '10:00:00',
    ]);

    (new RegenerateStaffSlots($staff->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    // This Wednesday (2026-05-14) and next Wednesday (2026-05-21) should both get slots
    $dates = TimeSlot::where('user_id', $staff->id)->pluck('date')->map->format('Y-m-d')->sort()->values();
    expect($dates->contains('2026-05-14'))->toBeTrue();
    expect($dates->contains('2026-05-21'))->toBeTrue();

    Carbon::setTestNow();
});

it('does not affect slots of other staff members', function () {
    $staff1 = User::factory()->create();
    $staff2 = User::factory()->create();

    $otherSlot = TimeSlot::factory()->create([
        'user_id'        => $staff2->id,
        'date'           => Carbon::today()->addDay(),
        'appointment_id' => null,
        'is_available'   => true,
    ]);

    (new RegenerateStaffSlots($staff1->id, 60))->handle(
        app(\App\Services\SlotGeneratorService::class)
    );

    expect(TimeSlot::find($otherSlot->id))->not->toBeNull();
});

it('failed hook logs the error', function () {
    \Illuminate\Support\Facades\Log::shouldReceive('error')
        ->once()
        ->with('RegenerateStaffSlots failed', \Mockery::on(fn ($ctx) =>
            isset($ctx['staff_id']) && isset($ctx['error'])
        ));

    $job = new RegenerateStaffSlots(1, 60);
    $job->failed(new \Exception('DB error'));
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/RegenerateStaffSlotsTest.php
```

Expected: FAIL — class `RegenerateStaffSlots` not found.

- [ ] **Step 3: Create the job**

```php
// app/Jobs/RegenerateStaffSlots.php
<?php

namespace App\Jobs;

use App\Models\SystemSetting;
use App\Models\TimeSlot;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RegenerateStaffSlots implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $staffId,
        public readonly int $slotMinutes,
    ) {}

    public function handle(SlotGeneratorService $generator): void
    {
        TimeSlot::where('user_id', $this->staffId)
            ->whereDate('date', '>=', Carbon::today())
            ->whereNull('appointment_id')
            ->delete();

        $horizon = SystemSetting::current()->slot_generation_weeks;
        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        for ($i = 0; $i < $horizon; $i++) {
            $generator->generateWeeklySlots(
                $this->staffId,
                $weekStart->copy()->addWeeks($i),
                $this->slotMinutes,
            );
        }

        Log::info('RegenerateStaffSlots completed', [
            'staff_id'    => $this->staffId,
            'slot_minutes' => $this->slotMinutes,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RegenerateStaffSlots failed', [
            'staff_id' => $this->staffId,
            'error'    => $e->getMessage(),
        ]);
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Jobs/RegenerateStaffSlotsTest.php
```

Expected: 7 passing.

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/RegenerateStaffSlots.php \
        tests/Feature/Jobs/RegenerateStaffSlotsTest.php
git commit -m "feat: add RegenerateStaffSlots job for on-demand slot rebuilding"
```

---

## Task 5: EditStaff — slot duration change confirmation modal

**Files:**
- Modify: `app/Filament/Resources/StaffResource/Pages/EditStaff.php`
- Modify: `tests/Feature/Filament/StaffResourceTest.php`

- [ ] **Step 1: Write failing tests**

Append these tests to `tests/Feature/Filament/StaffResourceTest.php`:

```php
it('mounts confirmSlotRegeneration action when slot_duration_minutes changes', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->preferences()->create(['slot_duration_minutes' => 60]);
    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.slot_duration_minutes', 30)
        ->call('save')
        ->assertActionMounted('confirmSlotRegeneration');
});

it('does not mount confirmSlotRegeneration when slot_duration_minutes is unchanged', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->preferences()->create(['slot_duration_minutes' => 60]);
    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.slot_duration_minutes', 60)
        ->call('save')
        ->assertActionNotMounted('confirmSlotRegeneration');
});

it('dispatches RegenerateStaffSlots when regeneration is confirmed', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->preferences()->create(['slot_duration_minutes' => 60]);
    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.slot_duration_minutes', 30)
        ->call('save')
        ->callAction('confirmSlotRegeneration');

    \Illuminate\Support\Facades\Queue::assertPushed(
        \App\Jobs\RegenerateStaffSlots::class,
        fn ($job) => $job->staffId === $staff->id && $job->slotMinutes === 30,
    );
});

it('does not dispatch RegenerateStaffSlots when duration change is not confirmed', function () {
    \Illuminate\Support\Facades\Queue::fake();

    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $staff = User::factory()->create();
    $staff->assignRole('staff');
    $staff->preferences()->create(['slot_duration_minutes' => 60]);
    $this->actingAs($admin);

    Livewire::test(EditStaff::class, ['record' => $staff->id])
        ->set('data.slot_duration_minutes', 30)
        ->call('save');
    // action is mounted but not called — user cancelled

    \Illuminate\Support\Facades\Queue::assertNotPushed(\App\Jobs\RegenerateStaffSlots::class);
});
```

Also add the missing import at the top of the test file:
```php
use App\Filament\Resources\StaffResource\Pages\EditStaff;
```

(It's already there — verify before adding.)

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffResourceTest.php --filter "confirmSlotRegeneration"
```

Expected: FAIL — action `confirmSlotRegeneration` not defined.

- [ ] **Step 3: Update EditStaff page**

```php
// app/Filament/Resources/StaffResource/Pages/EditStaff.php
<?php

namespace App\Filament\Resources\StaffResource\Pages;

use App\Filament\Resources\StaffResource;
use App\Jobs\RegenerateStaffSlots;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;

class EditStaff extends EditRecord
{
    protected static string $resource = StaffResource::class;

    private int $pendingSlotDurationMinutes = 60;

    private int $oldSlotDurationMinutes = 60;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label('Gestisci Disponibilità')
                ->icon('heroicon-o-clock')
                ->url(fn () => StaffResource::getUrl('manage-availability', ['record' => $this->getRecord()])),

            DeleteAction::make(),

            Action::make('confirmSlotRegeneration')
                ->visible(false)
                ->requiresConfirmation()
                ->modalHeading('Durata slot cambiata')
                ->modalDescription(fn () => sprintf(
                    'La durata degli slot è cambiata da %d min a %d min. Vuoi rigenerare gli slot futuri senza prenotazioni attive per questo staff?',
                    $this->oldSlotDurationMinutes,
                    $this->pendingSlotDurationMinutes,
                ))
                ->modalSubmitActionLabel('Sì, rigenera')
                ->modalCancelActionLabel('No, mantieni')
                ->action(fn () => RegenerateStaffSlots::dispatch(
                    $this->getRecord()->id,
                    $this->pendingSlotDurationMinutes,
                )),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['password'] = null;
        $data['password_confirmation'] = null;
        $data['slot_duration_minutes'] = $this->getRecord()->preferences->slot_duration_minutes ?? 60;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        $this->oldSlotDurationMinutes = $this->getRecord()->preferences?->slot_duration_minutes ?? 60;
        $this->pendingSlotDurationMinutes = $data['slot_duration_minutes'] ?? 60;
        unset($data['slot_duration_minutes']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->getRecord()->preferences()->updateOrCreate([], ['slot_duration_minutes' => $this->pendingSlotDurationMinutes]);

        if ($this->pendingSlotDurationMinutes !== $this->oldSlotDurationMinutes) {
            $this->mountAction('confirmSlotRegeneration');
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);

        $record = parent::handleRecordUpdate($record, $data);
        $record->syncRoles(['staff']);

        return $record;
    }
}
```

- [ ] **Step 4: Run new tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/StaffResourceTest.php
```

Expected: all tests passing.

- [ ] **Step 5: Run full test suite to check for regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all passing.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/StaffResource/Pages/EditStaff.php \
        tests/Feature/Filament/StaffResourceTest.php
git commit -m "feat: show confirmation modal when staff slot_duration_minutes changes"
```
