# Filament Admin Panel Customization — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Brand the Filament 4 admin panel as "Booking App" with blue colors and logo, and replace the default dashboard widgets with booking-domain stats and a recent appointments table.

**Architecture:** All branding lives in `AdminPanelProvider.php` (Filament native API — no custom CSS or Blade components). Two new widget classes in `app/Filament/Widgets/` are auto-discovered by the panel. Tests use the existing HTTP-based pattern from `ResourcesTest.php`.

**Tech Stack:** Laravel 13, Filament 4, PHP 8.4, Pest 4

---

## File Map

| File | Action |
|------|--------|
| `app/Providers/Filament/AdminPanelProvider.php` | Modify — branding + widget list |
| `app/Filament/Widgets/BookingStatsWidget.php` | Create |
| `app/Filament/Widgets/LatestAppointmentsWidget.php` | Create |
| `tests/Feature/Filament/WidgetsTest.php` | Create |

---

## Task 1: Branding (AdminPanelProvider)

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

No TDD needed — this is panel configuration, verified by visual inspection at `/admin`.

- [ ] **Step 1: Update AdminPanelProvider**

Replace the entire `panel()` method body in `app/Providers/Filament/AdminPanelProvider.php`:

```php
public function panel(Panel $panel): Panel
{
    return $panel
        ->default()
        ->id('admin')
        ->path('admin')
        ->login()
        ->brandName('Booking App')
        ->brandLogo(asset('img/logo.png'))
        ->brandLogoHeight('2rem')
        ->favicon(asset('img/logo.png'))
        ->colors([
            'primary' => Color::hex('#2563eb'),
        ])
        ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
        ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
        ->pages([
            Dashboard::class,
        ])
        ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
        ->widgets([])
        ->middleware([
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            AuthenticateSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            SubstituteBindings::class,
            DisableBladeIconComponents::class,
            DispatchServingFilamentEvent::class,
        ])
        ->authMiddleware([
            Authenticate::class,
        ]);
}
```

Note: `->widgets([])` clears `AccountWidget` and `FilamentInfoWidget`. The custom widgets are auto-discovered via `discoverWidgets()`.

- [ ] **Step 2: Run existing tests to verify nothing broke**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/ResourcesTest.php
```

Expected: all 5 tests pass.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: apply Booking App branding to Filament admin panel"
```

---

## Task 2: BookingStatsWidget

**Files:**
- Create: `app/Filament/Widgets/BookingStatsWidget.php`
- Create: `tests/Feature/Filament/WidgetsTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Filament/WidgetsTest.php`:

```php
<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

it('dashboard shows stats widget labels', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Appuntamenti oggi')
        ->assertSee('Appuntamenti questo mese')
        ->assertSee('Ricavi del mese');
});

it('stats widget counts today appointments correctly', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Appointment::factory()->count(3)->create(['scheduled_date' => today()]);
    Appointment::factory()->create(['scheduled_date' => today()->subMonth()]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('3');
});

it('stats widget sums completed payments for current month', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    Payment::factory()->create(['status' => 'completed', 'amount' => 150.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 50.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'pending', 'amount' => 999.00, 'created_at' => now()]);
    Payment::factory()->create(['status' => 'completed', 'amount' => 200.00, 'created_at' => now()->subMonth()]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('200,00'); // 150 + 50 = 200
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/WidgetsTest.php
```

Expected: FAIL — `Appuntamenti oggi` not found in response (widget class doesn't exist yet).

- [ ] **Step 3: Create BookingStatsWidget**

Create `app/Filament/Widgets/BookingStatsWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BookingStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make(
                'Appuntamenti oggi',
                Appointment::whereDate('scheduled_date', today())->count()
            ),
            Stat::make(
                'Appuntamenti questo mese',
                Appointment::whereMonth('scheduled_date', now()->month)
                    ->whereYear('scheduled_date', now()->year)
                    ->count()
            ),
            Stat::make(
                'Ricavi del mese',
                '€ ' . number_format(
                    Payment::completed()
                        ->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year)
                        ->sum('amount'),
                    2, ',', '.'
                )
            ),
        ];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/WidgetsTest.php
```

Expected: the 3 stats tests pass (the latest appointments test is not written yet — skip for now).

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Widgets/BookingStatsWidget.php tests/Feature/Filament/WidgetsTest.php
git commit -m "feat: add BookingStatsWidget to admin dashboard"
```

---

## Task 3: LatestAppointmentsWidget

**Files:**
- Create: `app/Filament/Widgets/LatestAppointmentsWidget.php`
- Modify: `tests/Feature/Filament/WidgetsTest.php`

- [ ] **Step 1: Add failing test to WidgetsTest.php**

Append to `tests/Feature/Filament/WidgetsTest.php`:

```php
it('dashboard shows latest appointments widget', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $customer = User::factory()->create(['name' => 'Mario Rossi']);
    $appointment = Appointment::factory()->create([
        'user_id' => $customer->id,
        'scheduled_date' => today(),
    ]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertSuccessful()
        ->assertSee('Mario Rossi')
        ->assertSee('Ultimi appuntamenti');
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/WidgetsTest.php --filter "latest appointments"
```

Expected: FAIL — `Ultimi appuntamenti` not visible.

- [ ] **Step 3: Create LatestAppointmentsWidget**

Create `app/Filament/Widgets/LatestAppointmentsWidget.php`:

```php
<?php

namespace App\Filament\Widgets;

use App\Models\Appointment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestAppointmentsWidget extends BaseWidget
{
    protected static ?string $heading = 'Ultimi appuntamenti';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Appointment::query()
                    ->with(['user', 'staff', 'service'])
                    ->latest()
                    ->limit(5)
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('user.name')
                    ->label('Cliente')
                    ->searchable(),

                TextColumn::make('staff.name')
                    ->label('Staff'),

                TextColumn::make('service.name')
                    ->label('Servizio'),

                TextColumn::make('scheduled_date')
                    ->label('Data')
                    ->dateTime('d/m/Y'),

                TextColumn::make('status')
                    ->label('Stato')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'In attesa',
                        'confirmed' => 'Confermato',
                        'cancelled' => 'Annullato',
                        'completed' => 'Completato',
                        default     => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'pending'   => 'warning',
                        'confirmed' => 'success',
                        'cancelled' => 'danger',
                        'completed' => 'gray',
                        default     => 'secondary',
                    }),
            ]);
    }
}
```

- [ ] **Step 4: Run all widget tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/WidgetsTest.php
```

Expected: all 4 tests pass.

- [ ] **Step 5: Run full test suite to catch regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Widgets/LatestAppointmentsWidget.php tests/Feature/Filament/WidgetsTest.php
git commit -m "feat: add LatestAppointmentsWidget to admin dashboard"
```

---

## Verification

- [ ] Open `http://localhost/admin` in a browser
- [ ] Confirm logo appears in sidebar and login page
- [ ] Confirm primary color is blue (buttons, links, active nav items)
- [ ] Confirm dashboard shows 3 stat cards and the appointments table
- [ ] Confirm default Filament widgets (account info, version) are gone
