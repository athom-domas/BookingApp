# Walk-in e Blocco Slot Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettere allo staff di creare appuntamenti walk-in dal calendario e di bloccare fasce orarie specifiche senza creare un vero appuntamento.

**Architecture:** Due HeaderAction sul `AppointmentCalendar` page (slide-over): uno per walk-in (crea Appointment con `is_walk_in=true`, cliente inline o esistente), uno per blocco slot (crea `StaffBlockout` con `start_time`/`end_time`). Il `SlotCalculationService` viene aggiornato per escludere le fasce orarie bloccate. I blocchi orari appaiono come eventi background nel calendario.

**Tech Stack:** Laravel 13, Filament 4, Livewire, saade/filament-fullcalendar, MySQL 8. Roles via Spatie Permission (`admin`, `staff`). Tutti i modelli usano `BelongsToBusiness` trait.

---

## File Structure

**Create:**
- `database/migrations/YYYY_MM_DD_000001_add_is_walk_in_to_appointments.php` — colonna `is_walk_in` boolean
- `database/migrations/YYYY_MM_DD_000002_add_time_range_to_staff_blockouts.php` — `start_time`/`end_time` nullable + index compound su staff_blockouts
- `app/Services/WalkInService.php` — logica creazione cliente inline (estratta dall'action per testabilità)

**Modify:**
- `app/Models/Appointment.php` — aggiunge `is_walk_in` a fillable e casts
- `app/Models/StaffBlockout.php` — aggiunge `start_time`, `end_time` a fillable e casts
- `app/Services/Booking/SlotCalculationService.php` — gestisce blockout orari (riga 147–154 + nuovo metodo privato `subtractRange`)
- `app/Filament/Pages/AppointmentCalendar.php` — aggiunge `getHeaderActions()` con due azioni
- `app/Filament/Widgets/AppointmentCalendarWidget.php` — `fetchEvents` include blockout events; `onEventClick` ignora click su blockout

**Test:**
- `tests/Feature/WalkInTest.php`
- `tests/Feature/SlotBlockingTest.php`

---

## Task 1: Migrazioni

**Files:**
- Create: `database/migrations/2026_06_19_000001_add_is_walk_in_to_appointments.php`
- Create: `database/migrations/2026_06_19_000002_add_time_range_to_staff_blockouts.php`

- [ ] **Step 1: Crea migrazione is_walk_in**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->boolean('is_walk_in')->default(false)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('is_walk_in');
        });
    }
};
```

- [ ] **Step 2: Crea migrazione start_time/end_time su staff_blockouts**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->time('start_time')->nullable()->after('end_date');
            $table->time('end_time')->nullable()->after('start_time');
            $table->index(['user_id', 'start_date', 'end_date'], 'staff_blockouts_user_date_range_idx');
        });
    }

    public function down(): void
    {
        Schema::table('staff_blockouts', function (Blueprint $table) {
            $table->dropIndex('staff_blockouts_user_date_range_idx');
            $table->dropColumn(['start_time', 'end_time']);
        });
    }
};
```

- [ ] **Step 3: Esegui le migrazioni**

```bash
docker-compose run --rm app php artisan migrate
```

Verifica: nessun errore, tabelle aggiornate.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_19_000001_add_is_walk_in_to_appointments.php
git add database/migrations/2026_06_19_000002_add_time_range_to_staff_blockouts.php
git commit -m "feat: add is_walk_in to appointments and time range to staff_blockouts"
```

---

## Task 2: Aggiornamento Modelli

**Files:**
- Modify: `app/Models/Appointment.php`
- Modify: `app/Models/StaffBlockout.php`
- Modify: `app/Models/User.php`

**Context:** `Appointment` usa `#[Fillable([...])]` come attributo PHP 8 PRIMA della keyword `class` (non dentro il body). Stesso pattern per `StaffBlockout`. I cast usano `protected function casts(): array`.

- [ ] **Step 1: Aggiungi is_walk_in ad Appointment**

In `app/Models/Appointment.php`, il `#[Fillable]` attuale include:
`'user_id', 'service_ids', 'staff_id', 'scheduled_date', 'status', 'customer_confirmed_at', 'final_price', 'notes', 'google_event_id', 'customer_google_event_id', 'business_id'`

Aggiungilo — metti `'is_walk_in'` in fondo all'array nel `#[Fillable]`.

Nella `protected function casts(): array` aggiungere:
```php
'is_walk_in' => 'boolean',
```

- [ ] **Step 2: Aggiungi start_time/end_time a StaffBlockout**

In `app/Models/StaffBlockout.php`, aggiorna l'attributo `#[Fillable]`:

```php
#[Fillable(['business_id', 'user_id', 'start_date', 'end_date', 'start_time', 'end_time', 'reason'])]
```

Nella `protected function casts(): array`:
```php
protected function casts(): array
{
    return [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];
}
```

`start_time` e `end_time` non richiedono cast — rimangono stringhe `H:i:s` come MySQL le restituisce.

- [ ] **Step 3: Aggiungi hasPlaceholderEmail() a User**

In `app/Models/User.php`, aggiungi il metodo pubblico:

```php
public function hasPlaceholderEmail(): bool
{
    return str_ends_with($this->email, '@noreply.local');
}
```

Questo helper verrà usato nel Task 4 per escludere i walk-in senza email reale dai meccanismi di notifica automatica (conferma appuntamento, follow-up reminder, ecc.).

- [ ] **Step 4: Commit**

```bash
git add app/Models/Appointment.php app/Models/StaffBlockout.php app/Models/User.php
git commit -m "feat: add is_walk_in and time-range blockout fields to models"
```

---

## Task 3: SlotCalculationService — Blockout Orari

**Files:**
- Modify: `app/Services/Booking/SlotCalculationService.php` (metodo `getWorkRanges` a riga 145, più nuovo metodo privato)

**Context:** Il metodo `getWorkRanges(User $staff, Carbon $date)` alle righe 147–154 fa un semplice `exists()` check su `StaffBlockout` e se trova un blockout restituisce `[]` (staff completamente bloccato). Va aggiornato per distinguere blockout intera giornata (`start_time IS NULL`) da blockout orario (`start_time NOT NULL`).

- [ ] **Step 1: Scrivi i test che falliscono**

Crea `tests/Feature/SlotBlockingTest.php`:

```php
<?php

use App\Models\AvailabilityRule;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    app()->instance('current_business_id', 1);
});

it('excludes full day when blockout has no time range', function () {
    $staff = User::factory()->create(['business_id' => 1]);
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => null,
        'end_time'   => null,
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toBeEmpty();
});

it('subtracts time-range blockout from work ranges', function () {
    $staff = User::factory()->create(['business_id' => 1]);
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toHaveCount(2)
        ->and($ranges[0]['end']->format('H:i'))->toBe('13:00')
        ->and($ranges[1]['start']->format('H:i'))->toBe('14:00');
});

it('keeps work ranges untouched when time-range blockout is on a different day', function () {
    $staff = User::factory()->create(['business_id' => 1]);
    $staff->assignRole('staff');

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-24', // different day
        'end_date'   => '2026-06-24',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    $service = new SlotCalculationService();
    $ranges = $service->getWorkRangesForOperator($staff, Carbon::parse('2026-06-23'));

    expect($ranges)->toHaveCount(1);
});
```

- [ ] **Step 2: Esegui test, verifica che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/SlotBlockingTest.php
```

Atteso: almeno il test 2 fallisce (il blockout con `start_time != null` attiva ancora il ritorno `[]` invece di sottrarre la fascia). Il test 3 potrebbe già passare perché il blockout è su un giorno diverso — entrambi i casi sono corretti.

- [ ] **Step 3: Aggiorna getWorkRanges in SlotCalculationService**

Sostituisci il metodo `getWorkRanges` (righe 145–176):

```php
private function getWorkRanges(User $staff, Carbon $date): array
{
    // Full-day blockout: start_time IS NULL — staff completamente bloccato
    $hasFullDayBlockout = StaffBlockout::where('user_id', $staff->id)
        ->where('start_date', '<=', $date->toDateString())
        ->where('end_date', '>=', $date->toDateString())
        ->whereNull('start_time')
        ->exists();

    if ($hasFullDayBlockout) {
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

    // Time-range blockouts: sottrai le fasce orarie bloccate
    $timeBlockouts = StaffBlockout::where('user_id', $staff->id)
        ->where('start_date', '<=', $date->toDateString())
        ->where('end_date', '>=', $date->toDateString())
        ->whereNotNull('start_time')
        ->whereNotNull('end_time')
        ->get();

    foreach ($timeBlockouts as $blockout) {
        $blockStart = $date->copy()->setTimeFromTimeString($blockout->start_time);
        $blockEnd   = $date->copy()->setTimeFromTimeString($blockout->end_time);
        $ranges     = $this->subtractRange($ranges, $blockStart, $blockEnd);
    }

    return $ranges;
}
```

- [ ] **Step 4: Aggiungi il metodo privato subtractRange**

Aggiungi in fondo alla classe (prima della parentesi chiusa):

```php
private function subtractRange(array $ranges, Carbon $blockStart, Carbon $blockEnd): array
{
    $result = [];
    foreach ($ranges as $range) {
        if ($blockEnd <= $range['start'] || $blockStart >= $range['end']) {
            $result[] = $range;
        } elseif ($blockStart <= $range['start'] && $blockEnd >= $range['end']) {
            // blockout copre tutto il range — droppato
        } elseif ($blockStart > $range['start'] && $blockEnd < $range['end']) {
            // blockout in mezzo — split
            $result[] = ['start' => $range['start']->copy(), 'end' => $blockStart->copy()];
            $result[] = ['start' => $blockEnd->copy(),       'end' => $range['end']->copy()];
        } elseif ($blockStart <= $range['start']) {
            $result[] = ['start' => $blockEnd->copy(), 'end' => $range['end']->copy()];
        } else {
            $result[] = ['start' => $range['start']->copy(), 'end' => $blockStart->copy()];
        }
    }
    return $result;
}
```

- [ ] **Step 5: Controlla che ci sia una StaffBlockoutFactory — aggiungila se manca**

Cerca `database/factories/StaffBlockoutFactory.php`. Se esiste, aggiungi `start_time` e `end_time` nullable alla definizione. Se non esiste, creala:

```php
<?php

namespace Database\Factories;

use App\Models\StaffBlockout;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffBlockout> */
class StaffBlockoutFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'     => 1,
            'start_date'  => today(),
            'end_date'    => today(),
            'start_time'  => null,
            'end_time'    => null,
            'reason'      => null,
        ];
    }
}
```

Poi aggiungi il docblock al modello `StaffBlockout`:
```php
/** @use HasFactory<\Database\Factories\StaffBlockoutFactory> */
use BelongsToBusiness, HasFactory;
```

- [ ] **Step 6: Esegui test, verifica che passano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/SlotBlockingTest.php
```

Atteso: tutti e 3 i test passano.

- [ ] **Step 7: Esegui la suite completa per verificare regressioni**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Atteso: tutti i test passano.

- [ ] **Step 8: Commit**

```bash
git add app/Services/Booking/SlotCalculationService.php tests/Feature/SlotBlockingTest.php database/factories/StaffBlockoutFactory.php app/Models/StaffBlockout.php
git commit -m "feat: support time-range slot blocking in SlotCalculationService"
```

---

## Task 4: Walk-in Action

**Files:**
- Create: `app/Services/WalkInService.php`
- Modify: `app/Filament/Pages/AppointmentCalendar.php`

**Context:** `AppointmentCalendar` estende `Filament\Pages\Page` e implementa `HasForms`. Attualmente non ha `getHeaderActions()`. Le action su Page usano `Filament\Actions\Action`. Il refresh del calendario si triggera con `$this->dispatch('filament-fullcalendar--refresh')->to(AppointmentCalendarWidget::class)`. L'email in `users` è `NOT NULL` con unique `(email, business_id)` — per walk-in senza email `WalkInService` genera un placeholder con `Str::ulid()`.

**Imports già presenti** in `AppointmentCalendar.php` (non re-aggiungere): `App\Models\User`, `App\Models\Service`, `Filament\Facades\Filament`, `Filament\Forms\Components\Select`.

- [ ] **Step 1: Scrivi test che falliscono**

Crea `tests/Feature/WalkInTest.php`:

```php
<?php

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Services\WalkInService;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff',    'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    app()->instance('current_business_id', 1);
});

it('generates a placeholder email when none provided', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Mario Rossi', null, 1);

    expect($user->email)->toMatch('/^walkin_[0-9A-Z]+@noreply\.local$/')
        ->and($user->name)->toBe('Mario Rossi')
        ->and($user->business_id)->toBe(1);
});

it('uses provided email when creating inline customer', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Mario Rossi', 'mario@example.com', 1);

    expect($user->email)->toBe('mario@example.com');
});

it('assigns customer role to inline customer', function () {
    $svc  = new WalkInService();
    $user = $svc->createInlineCustomer('Test Cliente', null, 1);

    expect($user->hasRole('customer'))->toBeTrue();
});

it('generated placeholder emails are unique across multiple calls', function () {
    $svc    = new WalkInService();
    $emails = collect(range(1, 5))
        ->map(fn () => $svc->createInlineCustomer('Test', null, 1)->email);

    expect($emails->unique()->count())->toBe(5);
});

it('creates walk-in appointment with is_walk_in true and confirmed status', function () {
    $staff    = User::factory()->create(['business_id' => 1]);
    $staff->assignRole('staff');
    $customer = User::factory()->create(['business_id' => 1]);
    $customer->assignRole('customer');
    $service  = Service::factory()->create(['business_id' => 1, 'active' => true]);

    $appt = Appointment::create([
        'business_id'    => 1,
        'user_id'        => $customer->id,
        'staff_id'       => $staff->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => now()->addHour(),
        'status'         => 'confirmed',
        'is_walk_in'     => true,
    ]);

    expect($appt->is_walk_in)->toBeTrue()
        ->and($appt->status)->toBe('confirmed');
});
```

- [ ] **Step 2: Esegui test, verifica che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WalkInTest.php
```

Atteso: `Class "App\Services\WalkInService" not found` — corretto, il service viene creato nel prossimo step.

- [ ] **Step 3: Crea WalkInService**

Crea `app/Services/WalkInService.php`:

```php
<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;

class WalkInService
{
    public function createInlineCustomer(string $name, ?string $email, int $businessId): User
    {
        $email ??= 'walkin_' . Str::ulid() . '@noreply.local';

        $user = User::create([
            'name'        => $name,
            'email'       => $email,
            'password'    => bcrypt(Str::random(16)),
            'business_id' => $businessId,
        ]);
        $user->assignRole('customer');

        return $user;
    }
}
```

- [ ] **Step 4: Esegui test, verifica che passano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WalkInTest.php
```

Se `creates walk-in appointment` fallisce perché `is_walk_in` non è fillable, significa che Task 2 non è stato completato — torna indietro.

- [ ] **Step 5: Aggiungi getHeaderActions() ad AppointmentCalendar**

Aggiungi i seguenti import in cima al file (solo quelli non già presenti — vedi Context):

```php
use App\Models\Appointment;
use App\Models\StaffBlockout;
use App\Services\WalkInService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
```

Aggiungi il metodo nella classe (prima di `filtersForm`):

```php
protected function getHeaderActions(): array
{
    $user = Filament::auth()->user();

    return [
        Action::make('createWalkin')
            ->label('Walk-in')
            ->icon('heroicon-o-user-plus')
            ->slideOver()
            ->visible(fn () => Filament::auth()->user()?->isAdmin()
                || Filament::auth()->user()?->isStaff())
            ->form([
                DateTimePicker::make('scheduled_date')
                    ->label('Data e ora')
                    ->required()
                    ->seconds(false)
                    ->default(now()),

                Select::make('staff_id')
                    ->label('Operatore')
                    ->options(fn () => User::role(['admin', 'staff'])
                        ->where('business_id', Filament::auth()->user()?->business_id)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->default($user?->isStaff() ? $user->id : null),

                Select::make('service_ids')
                    ->label('Servizi')
                    ->options(fn () => Service::where('business_id', Filament::auth()->user()?->business_id)
                        ->where('active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->multiple()
                    ->required(),

                Select::make('user_id')
                    ->label('Cliente')
                    ->options(fn () => User::role('customer')
                        ->where('business_id', Filament::auth()->user()?->business_id)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->required()
                    ->createOptionForm([
                        TextInput::make('name')
                            ->label('Nome')
                            ->required(),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->unique(
                                table: 'users',
                                column: 'email',
                                modifyRuleUsing: fn ($rule) => $rule->where('business_id', Filament::auth()->user()?->business_id),
                            ),
                    ])
                    ->createOptionUsing(function (array $data): int {
                        return app(WalkInService::class)
                            ->createInlineCustomer(
                                $data['name'],
                                $data['email'] ?: null,
                                Filament::auth()->user()?->business_id,
                            )
                            ->id;
                    }),

                Textarea::make('notes')
                    ->label('Note')
                    ->rows(2),
            ])
            ->action(function (array $data): void {
                $customer = User::find($data['user_id']);

                Appointment::create([
                    'business_id'    => Filament::auth()->user()?->business_id,
                    'user_id'        => $data['user_id'],
                    'staff_id'       => $data['staff_id'],
                    'service_ids'    => $data['service_ids'],
                    'scheduled_date' => $data['scheduled_date'],
                    'status'         => 'confirmed',
                    'is_walk_in'     => true,
                    'notes'          => $data['notes'] ?? null,
                ]);

                // Se il cliente ha email placeholder (@noreply.local), non inviare
                // notifiche automatiche — l'observer o job devono controllare
                // $appointment->user->hasPlaceholderEmail() prima di inviare email.

                Notification::make()
                    ->title('Walk-in creato')
                    ->success()
                    ->send();

                $this->dispatch('filament-fullcalendar--refresh')
                    ->to(AppointmentCalendarWidget::class);
            }),
    ];
}
```

- [ ] **Step 6: Verifica visivamente**

Avvia i container (`docker-compose up -d`), accedi al panel admin su `http://localhost/admin`, naviga su "Calendario". Dovrebbe apparire il pulsante "Walk-in" in alto a destra. Cliccalo — si apre lo slide-over con il form.

Prova:
1. Crea walk-in con cliente esistente — l'appuntamento appare nel calendario
2. Crea walk-in con nuovo cliente (clicca "Crea" nel dropdown cliente) — il nuovo utente viene creato e l'appuntamento appare

- [ ] **Step 7: Commit**

```bash
git add app/Services/WalkInService.php app/Filament/Pages/AppointmentCalendar.php tests/Feature/WalkInTest.php
git commit -m "feat: add walk-in appointment creation to calendar page"
```

---

## Task 5: Quick Slot Blocking Action

**Files:**
- Modify: `app/Filament/Pages/AppointmentCalendar.php`

**Context:** Aggiunge una seconda `Action` a `getHeaderActions()`. Il blocco slot crea un `StaffBlockout` con `start_date = end_date` (giornata singola) e `start_time`/`end_time` impostati. Lo staff vede solo se stesso; gli admin vedono tutti.

- [ ] **Step 1: Aggiungi blockSlot action a getHeaderActions()**

Nel `return [...]` di `getHeaderActions()`, dopo l'action `createWalkin`, aggiungi:

```php
Action::make('blockSlot')
    ->label('Blocca slot')
    ->icon('heroicon-o-lock-closed')
    ->color('danger')
    ->slideOver()
    ->visible(fn () => Filament::auth()->user()?->isAdmin()
        || Filament::auth()->user()?->isStaff())
    ->form([
        DatePicker::make('date')
            ->label('Data')
            ->required()
            ->default(today()),

        Select::make('staff_id')
            ->label('Operatore')
            ->options(fn () => User::role(['admin', 'staff'])
                ->where('business_id', Filament::auth()->user()?->business_id)
                ->orderBy('name')
                ->pluck('name', 'id'))
            ->required()
            ->default(Filament::auth()->user()?->isStaff()
                ? Filament::auth()->user()?->id
                : null)
            ->visible(fn () => Filament::auth()->user()?->isAdmin()
                || Filament::auth()->user()?->can('appointments.view_all')),

        TimePicker::make('start_time')
            ->label('Dalle')
            ->required()
            ->seconds(false),

        TimePicker::make('end_time')
            ->label('Alle')
            ->required()
            ->seconds(false)
            ->after('start_time'),

        TextInput::make('reason')
            ->label('Motivo')
            ->placeholder('es. Pausa pranzo'),
    ])
    ->action(function (array $data): void {
        $staffId = $data['staff_id']
            ?? (Filament::auth()->user()?->isStaff()
                ? Filament::auth()->user()?->id
                : null);

        StaffBlockout::create([
            'business_id' => Filament::auth()->user()?->business_id,
            'user_id'     => $staffId,
            'start_date'  => $data['date'],
            'end_date'    => $data['date'],
            'start_time'  => $data['start_time'],
            'end_time'    => $data['end_time'],
            'reason'      => $data['reason'] ?? null,
        ]);

        Notification::make()
            ->title('Slot bloccato')
            ->success()
            ->send();

        $this->dispatch('filament-fullcalendar--refresh')
            ->to(AppointmentCalendarWidget::class);
    }),
```

- [ ] **Step 2: Verifica visivamente**

Accedi al panel admin → Calendario. Usa il pulsante "Blocca slot" per bloccare una fascia oraria (es. oggi 14:00–15:00). Verifica nel database:

```bash
docker-compose run --rm app php artisan tinker --execute="dump(App\Models\StaffBlockout::latest()->first()->toArray());"
```

Atteso: `start_time = '14:00:00'`, `end_time = '15:00:00'`, `business_id` corretto.

Poi controlla che lo slot non sia più disponibile nel portale clienti (se visibile).

**Nota overlap:** se nella fascia bloccata esistono già appuntamenti confermati, il blockout non li modifica né li cancella. L'azione blocca solo i nuovi slot disponibili per i clienti. È il comportamento atteso — lo staff crea il blockout consapevolmente.

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/AppointmentCalendar.php
git commit -m "feat: add quick slot blocking action to calendar page"
```

---

## Task 6: Blocchi Orari nel Calendario

**Files:**
- Modify: `app/Filament/Widgets/AppointmentCalendarWidget.php`

**Context:** Il metodo `fetchEvents` (riga 85) restituisce solo appuntamenti. Va aggiornato per includere i blocchi orari come eventi background. Il metodo `onEventClick` (riga 214) va aggiornato per ignorare i click su eventi blockout.

- [ ] **Step 1: Aggiungi l'import di StaffBlockout al widget**

In `app/Filament/Widgets/AppointmentCalendarWidget.php`, aggiungi tra gli import:

```php
use App\Models\StaffBlockout;
use Illuminate\Support\Carbon;
```

- [ ] **Step 2: Aggiorna fetchEvents per includere blockout events**

Alla fine di `fetchEvents`, dopo `$appointments = $query->get();` e prima della costruzione degli eventi appointments, aggiungi il codice per i blockout. Sostituisci il `return` finale con:

```php
$appointmentEvents = $appointments->map(function ($appointment) use ($services) {
    $duration = collect($appointment->service_ids ?? [])
        ->sum(fn($id) => $services->get($id)?->duration_minutes ?? 30);

    $serviceNames = collect($appointment->service_ids ?? [])
        ->map(fn($id) => $services->get($id)?->name)
        ->filter()
        ->implode(', ');

    return [
        'id'              => $appointment->id,
        'resourceId'      => (string) $appointment->staff_id,
        'title'           => $appointment->user->name . ' – ' . $serviceNames,
        'start'           => $appointment->scheduled_date->toIso8601String(),
        'end'             => $appointment->scheduled_date->copy()->addMinutes($duration)->toIso8601String(),
        'backgroundColor' => $this->staffColor($appointment),
        'borderColor'     => $this->staffColor($appointment),
        'classNames'      => ['fc-appt-' . $appointment->status],
        'extendedProps'   => [
            'status' => $appointment->status,
            'type'   => 'appointment',
        ],
    ];
})->toArray();

// Blockout orari — eventi background (solo quelli con fascia oraria definita; i full-day blockout senza start_time/end_time non sono mostrati nel calendario in questa iterazione)
$blockoutQuery = StaffBlockout::where('business_id', $user->business_id)
    ->whereNotNull('start_time')
    ->whereNotNull('end_time')
    ->whereDate('start_date', '<=', $fetchInfo['end'])
    ->whereDate('end_date', '>=', $fetchInfo['start']);

// Stessa logica staff filter degli appuntamenti
if ($user->isAdmin()) {
    if (!empty($this->filterStaff)) {
        $blockoutQuery->whereIn('user_id', $this->filterStaff);
    }
} elseif ($user->isStaff()) {
    if (!$user->can('appointments.view_all')) {
        $blockoutQuery->where('user_id', $user->id);
    } elseif (!empty($this->filterStaff)) {
        $blockoutQuery->whereIn('user_id', $this->filterStaff);
    }
}

$blockoutEvents = $blockoutQuery->get()->flatMap(function (StaffBlockout $blockout) {
    $events  = [];
    $current = Carbon::parse($blockout->start_date);
    $endDate = Carbon::parse($blockout->end_date);

    while ($current->lte($endDate)) {
        $events[] = [
            'id'              => 'blockout-' . $blockout->id . '-' . $current->toDateString(),
            'resourceId'      => (string) $blockout->user_id,
            'title'           => $blockout->reason ?: 'Slot bloccato',
            'start'           => $current->copy()->setTimeFromTimeString($blockout->start_time)->toIso8601String(),
            'end'             => $current->copy()->setTimeFromTimeString($blockout->end_time)->toIso8601String(),
            'display'         => 'background',
            'backgroundColor' => '#ef4444',
            'classNames'      => ['fc-blockout'],
            'extendedProps'   => ['type' => 'blockout', 'blockoutId' => $blockout->id],
        ];
        $current->addDay();
    }

    return $events;
})->toArray();

return array_merge($appointmentEvents, $blockoutEvents);
```

Nota: il codice originale che costruisce gli eventi appointments (il map su `$appointments`) va rimosso e sostituito con `$appointmentEvents` sopra.

- [ ] **Step 3: Aggiorna onEventClick per ignorare blockout**

In `onEventClick` (riga 214), aggiungi all'inizio del metodo:

```php
public function onEventClick(array $event): void
{
    // Ignora click su eventi blockout
    if (($event['extendedProps']['type'] ?? null) === 'blockout') {
        return;
    }

    $status = $event['extendedProps']['status'] ?? null;
    // ... resto del metodo invariato ...
```

- [ ] **Step 4: Verifica visivamente**

Crea un blocco slot via UI. Passa alla vista "Settimana" o "Giorno" nel calendario. La fascia oraria bloccata dovrebbe apparire come sfondo rosso semitrasparente nella colonna dello staff corrispondente. Cliccando sullo slot bloccato non deve aprirsi nessun modal.

- [ ] **Step 5: Esegui la suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Atteso: tutti i test passano.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php
git commit -m "feat: show time-range blockouts as background events in calendar"
```

---

## Task 7: Test Aggiuntivi Walk-in e Integrazione

**Files:**
- Modify: `tests/Feature/WalkInTest.php`

- [ ] **Step 1: Aggiungi test per il blocco slot nella test suite**

Aggiungi a `tests/Feature/SlotBlockingTest.php`:

```php
it('blocks only the specified time range, leaving the rest available', function () {
    $staff   = User::factory()->create(['business_id' => 1]);
    $staff->assignRole('staff');
    $service = Service::factory()->create([
        'business_id'      => 1,
        'duration_minutes' => 60,
        'active'           => true,
    ]);

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // Tuesday (2026-06-23 è martedì)
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    $svc = new SlotCalculationService();
    $slots = $svc->getAvailableSlots([
        'date'            => '2026-06-23',
        'serviceIds'      => [$service->id],
        'staffId'         => $staff->id,
        'staffPreference' => 'specific',
    ]);

    $slotTimes = collect($slots)->pluck('time')->toArray();
    expect($slotTimes)->not->toContain('13:00')
        ->and($slotTimes)->not->toContain('13:30')
        ->and($slotTimes)->toContain('14:00');
});
```

- [ ] **Step 2: Esegui test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/SlotBlockingTest.php tests/Feature/WalkInTest.php -v
```

Atteso: tutti i test passano.

- [ ] **Step 3: Suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

- [ ] **Step 4: Commit finale**

```bash
git add tests/Feature/SlotBlockingTest.php tests/Feature/WalkInTest.php
git commit -m "test: add integration tests for walk-in and slot blocking"
```

---

## Self-Review

**Spec coverage:**
- ✅ Walk-in creation: Task 4 — slide-over su AppointmentCalendar con form completo
- ✅ Walk-in creation: Task 4 — slide-over su AppointmentCalendar con form completo, solo admin/staff
- ✅ Cliente nuovo inline: Task 4 — `WalkInService::createInlineCustomer()` chiamato da `createOptionUsing`
- ✅ Email placeholder univoca: `Str::ulid()`, unicità garantita anche con chiamate ravvicinate
- ✅ Email duplicata inline customer: validazione `->unique()` scoped per `business_id`
- ✅ Email placeholder e notifiche: `User::hasPlaceholderEmail()` (Task 2 Step 3); observer/job devono verificare prima di inviare
- ✅ Slot blocking con fascia oraria: Task 5 — action con date + start_time/end_time + business_id
- ✅ Bypass availability check per walk-in: Task 4 — crea direttamente senza chiamare SlotCalculationService
- ✅ Servizi filtrati per business: `Service::where('business_id', ...)`  — nessuna cross-tenant leakage
- ✅ SlotCalculationService rispetta blockout orari: Task 3 — `whereNotNull('start_time')` + `whereNotNull('end_time')`
- ✅ Blocchi visibili nel calendario: Task 6 — `StaffBlockout::where('business_id', ...)` + filtro `whereNotNull` su entrambe le colonne
- ✅ Click su blockout non apre modal: Task 6, `onEventClick` guard
- ✅ Blockout intera giornata non toccati: Task 3 — `whereNull('start_time')`; non mostrati nel calendario (esplicitato Task 6)
- ✅ Overlap blockout/appuntamenti: by design — blockout non tocca appuntamenti esistenti (nota Task 5)
- ✅ Permessi HeaderAction: entrambe protette da `->visible()` con `isAdmin()||isStaff()`
- ✅ DB index con nome esplicito: Task 1 — `staff_blockouts_user_date_range_idx`, droppato nel `down()`
- ✅ Test walk-in: Task 4 — testano `WalkInService` direttamente (placeholder email, unicità, role, model)

**Out of scope confermato:** drag-and-drop rescheduling, sostituzione UI calendario, full-day blockout nel calendario (iterazione futura).
