# Calendar Drag-and-Drop Reschedule — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff e admin possono trascinare un appuntamento nella vista calendario per spostarlo a un nuovo orario, con validazione lato server (fasce lavorative, blockout, conflitti), salvataggio immediato e undo toast.

**Architecture:** `AppointmentRescheduleService` centralizza validazione e salvataggio tramite `SlotCalculationService` esistente. Il widget `AppointmentCalendarWidget` espone `onEventDrop()` (delega al service, `return false` su errore per triggherare il revert FullCalendar) e `undoReschedule()` (undo validato tramite lo stesso service). FullCalendar controlla il drag tramite flag per-evento e per-view.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, `saade/filament-fullcalendar` v4 beta, `SlotCalculationService` (esistente in `app/Services/Booking/`).

---

## Contesto codebase

- All commands run inside Docker: `docker-compose run --rm app <cmd>`
- Test DB: sempre `-e DB_DATABASE=booking_app_test` con pest
- Widget: `app/Filament/Widgets/AppointmentCalendarWidget.php` (446 righe)
  - `config()` alla riga 38 — return array senza `editable` né `views`
  - `fetchEvents()` alla riga 86 — mappa appointment events e blockout events separatamente
  - `onEventClick()` alla riga 236
  - Import esistenti: `use Livewire\Attributes\On;` (riga 21) — già presente, non aggiungere
  - **Mancano**: `Carbon\Carbon`, `RescheduleConflictException`, `AppointmentRescheduleService`, `NotificationAction`
- Slot granularity: `SlotCalculationService::calculateTotalDuration()` è già `public`
- Migrazioni esistenti: `000001_add_is_walk_in`, `000002_add_time_range_to_staff_blockouts` — la nuova è `000003`

---

## File

| Azione | Path |
|--------|------|
| Crea | `database/migrations/2026_06_19_000003_add_reschedule_index_to_appointments.php` |
| Crea | `app/Exceptions/RescheduleConflictException.php` |
| Crea | `app/Services/AppointmentRescheduleService.php` |
| Crea | `tests/Feature/AppointmentRescheduleTest.php` |
| Modifica | `app/Filament/Widgets/AppointmentCalendarWidget.php` |

---

## Task 1: Migration — indice su appointments per le query di reschedule

**Files:**
- Crea: `database/migrations/2026_06_19_000003_add_reschedule_index_to_appointments.php`

- [ ] **Step 1: Crea la migration**

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
            $table->index(
                ['business_id', 'staff_id', 'scheduled_date', 'status'],
                'appts_reschedule_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropIndex('appts_reschedule_idx');
        });
    }
};
```

- [ ] **Step 2: Esegui la migration**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `Migrating: 2026_06_19_000003_add_reschedule_index_to_appointments` seguito da `Migrated`.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_19_000003_add_reschedule_index_to_appointments.php
git commit -m "feat: add reschedule conflict index to appointments table"
```

---

## Task 2: Exception class — `RescheduleConflictException`

**Files:**
- Crea: `app/Exceptions/RescheduleConflictException.php`

Questa è una classe dati pura — nessun test diretto. Viene testata indirettamente in Task 3.

- [ ] **Step 1: Crea la classe**

```php
<?php

namespace App\Exceptions;

class RescheduleConflictException extends \RuntimeException
{
    public const FORBIDDEN     = 'forbidden';
    public const WRONG_STATUS  = 'wrong_status';
    public const OUTSIDE_HOURS = 'outside_hours';
    public const CONFLICT      = 'conflict';

    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add app/Exceptions/RescheduleConflictException.php
git commit -m "feat: add RescheduleConflictException with reason codes"
```

---

## Task 3: `AppointmentRescheduleService` — TDD

**Files:**
- Crea: `tests/Feature/AppointmentRescheduleTest.php`
- Crea: `app/Services/AppointmentRescheduleService.php`

**Dipendenze:** Task 1 e Task 2 devono essere completati (la migration deve girare e l'eccezione deve esistere).

- [ ] **Step 1: Crea il file di test con tutti e 13 i test**

Crea `tests/Feature/AppointmentRescheduleTest.php` con il contenuto seguente. Le funzioni helper (`expectRescheduleReason`, `makeStaffWithService`, `makeAppointment`) sono PHP function globali definite a livello di file, disponibili a tutti i test del file. Se si verificano naming conflicts con altri file di test, wrappa tutto in un `describe('AppointmentRescheduleService', function () { ... })`.

```php
<?php

use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Business;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\AppointmentRescheduleService;
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Permission::firstOrCreate(['name' => 'appointments.view_all', 'guard_name' => 'web']);

    $business = Business::factory()->create();
    app()->instance('current_business_id', $business->id);
});

function expectRescheduleReason(Appointment $appointment, Carbon $newTime, User $actor, string $expectedReason): void
{
    $caught = null;
    try {
        app(AppointmentRescheduleService::class)->reschedule($appointment, $newTime, $actor);
    } catch (RescheduleConflictException $e) {
        $caught = $e;
    }
    expect($caught)->not->toBeNull('Attesa RescheduleConflictException non lanciata');
    expect($caught->reason)->toBe($expectedReason);
}

function makeStaffWithService(): array
{
    $businessId = app('current_business_id');

    $staff = User::factory()->create(['business_id' => $businessId]);
    $staff->assignRole('staff');

    $service = Service::factory()->create([
        'business_id'      => $businessId,
        'duration_minutes' => 60,
        'active'           => true,
    ]);
    $staff->services()->attach($service->id);

    AvailabilityRule::factory()->create([
        'user_id'      => $staff->id,
        'day_of_week'  => 2, // martedì — 2026-06-23
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    return [$staff, $service];
}

function makeAppointment(User $staff, Service $service, string $status = 'pending', string $time = '10:00'): Appointment
{
    return Appointment::factory()->create([
        'business_id'    => app('current_business_id'),
        'staff_id'       => $staff->id,
        'user_id'        => User::factory()->create(['business_id' => app('current_business_id')])->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => Carbon::parse("2026-06-23 {$time}"),
        'status'         => $status,
    ]);
}

// ─── Test 1 ───────────────────────────────────────────────────────────────

it('reschedules a pending appointment to an available slot', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('14:00');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('14:00');
});

// ─── Test 2 ───────────────────────────────────────────────────────────────

it('reschedules a confirmed appointment to an available slot', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'confirmed', '10:00');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('14:00');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('14:00');
});

// ─── Test 3 ───────────────────────────────────────────────────────────────

it('allows admin to reschedule any appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);

    $admin = User::factory()->create(['business_id' => app('current_business_id')]);
    $admin->assignRole('admin');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 15:00'),
        $admin,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('15:00');
});

// ─── Test 4 ───────────────────────────────────────────────────────────────

it('allows staff with appointments.view_all to reschedule any appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);

    $otherStaff = User::factory()->create(['business_id' => app('current_business_id')]);
    $otherStaff->assignRole('staff');
    $otherStaff->givePermissionTo('appointments.view_all');

    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 15:00'),
        $otherStaff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('15:00');
});

// ─── Test 5 ───────────────────────────────────────────────────────────────

it('throws FORBIDDEN when staff tries to reschedule another staff appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    $otherStaff = User::factory()->create(['business_id' => app('current_business_id')]);
    $otherStaff->assignRole('staff');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $otherStaff,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 6 ───────────────────────────────────────────────────────────────

it('throws FORBIDDEN when actor belongs to a different business', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    $otherBusiness = Business::factory()->create();
    $actor = User::factory()->create(['business_id' => $otherBusiness->id]);
    $actor->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $actor,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 7 ───────────────────────────────────────────────────────────────

it('throws WRONG_STATUS when rescheduling a completed appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'completed');
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 8 ───────────────────────────────────────────────────────────────

it('throws WRONG_STATUS when rescheduling a cancelled appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'cancelled');
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 9 ───────────────────────────────────────────────────────────────

it('throws OUTSIDE_HOURS when slot is outside working hours', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalTime = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 08:00'), // prima delle 09:00
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});

// ─── Test 10 ──────────────────────────────────────────────────────────────

it('throws OUTSIDE_HOURS when slot falls within a staff blockout', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    StaffBlockout::factory()->create([
        'user_id'     => $staff->id,
        'business_id' => app('current_business_id'),
        'start_date'  => '2026-06-23',
        'end_date'    => '2026-06-23',
        'start_time'  => '13:00',
        'end_time'    => '14:00',
    ]);

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 13:00'),
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});

// ─── Test 11 ──────────────────────────────────────────────────────────────

it('throws CONFLICT when slot overlaps another appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Secondo appuntamento: 11:00–12:00 (60 min)
    makeAppointment($staff, $service, 'confirmed', '11:00');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 11:30'), // overlap con 11:00-12:00
        $staff,
        RescheduleConflictException::CONFLICT,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});

// ─── Test 12 ──────────────────────────────────────────────────────────────

it('does not conflict with itself when moved to an overlapping position', function () {
    [$staff, $service] = makeStaffWithService();
    // Appuntamento alle 10:00, 60 min → occupa 10:00–11:00
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Spostiamo alle 10:30 → 10:30–11:30
    // Senza auto-esclusione dal conflict check fallirebbe (10:00–11:00 apparirebbe "occupato")
    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 10:30'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('10:30');
    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:30');
});

// ─── Test 13 ──────────────────────────────────────────────────────────────

it('throws FORBIDDEN when appointment business differs from actor business', function () {
    [$staff, $service] = makeStaffWithService();

    $otherBusiness = Business::factory()->create();
    $otherStaff    = User::factory()->create(['business_id' => $otherBusiness->id]);
    $otherStaff->assignRole('staff');
    AvailabilityRule::factory()->create([
        'user_id'      => $otherStaff->id,
        'day_of_week'  => 2,
        'start_time'   => '09:00',
        'end_time'     => '18:00',
        'is_available' => true,
    ]);

    $appointment = Appointment::factory()->create([
        'business_id'    => $otherBusiness->id,
        'staff_id'       => $otherStaff->id,
        'user_id'        => User::factory()->create(['business_id' => $otherBusiness->id])->id,
        'service_ids'    => [$service->id],
        'scheduled_date' => Carbon::parse('2026-06-23 10:00'),
        'status'         => 'pending',
    ]);
    $originalTime = $appointment->scheduled_date->format('H:i');

    // Actor è admin del business principale, non di $otherBusiness
    $admin = User::factory()->create(['business_id' => app('current_business_id')]);
    $admin->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $admin,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalTime);
});
```

- [ ] **Step 2: Esegui i test — devono tutti fallire**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/AppointmentRescheduleTest.php
```

Expected: 13 test falliti con `Error: Class "App\Services\AppointmentRescheduleService" not found` o simile. Se alcuni passano prima dell'implementazione, investigare.

- [ ] **Step 3: Crea `AppointmentRescheduleService`**

```php
<?php

namespace App\Services;

use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\User;
use App\Services\Booking\SlotCalculationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AppointmentRescheduleService
{
    public function __construct(private SlotCalculationService $slots) {}

    public function reschedule(
        Appointment $appointment,
        Carbon $newDateTime,
        User $actor,
    ): Appointment {
        return DB::transaction(function () use ($appointment, $newDateTime, $actor): Appointment {
            $appointment = Appointment::where('id', $appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Permessi
            $canManageAny = $actor->isAdmin() || $actor->can('appointments.view_all');

            if (! $canManageAny && $appointment->staff_id !== $actor->id) {
                throw new RescheduleConflictException(
                    'Non sei autorizzato a spostare questo appuntamento.',
                    RescheduleConflictException::FORBIDDEN,
                );
            }

            if ($appointment->business_id !== $actor->business_id) {
                throw new RescheduleConflictException(
                    'Appuntamento non trovato.',
                    RescheduleConflictException::FORBIDDEN,
                );
            }

            // 2. Status
            if (! in_array($appointment->status, ['pending', 'confirmed'])) {
                throw new RescheduleConflictException(
                    'Solo gli appuntamenti in attesa o confermati possono essere spostati.',
                    RescheduleConflictException::WRONG_STATUS,
                );
            }

            // 3. Fasce lavorative + blockout
            $serviceIds = $appointment->service_ids
                ?? ($appointment->service_id ? [$appointment->service_id] : []);

            $duration = $this->slots->calculateTotalDuration($serviceIds);
            $slotEnd  = $newDateTime->copy()->addMinutes($duration);
            $date     = $newDateTime->copy()->startOfDay();

            $workRanges = $this->slots->getWorkRangesForOperator($appointment->staff, $date);

            $fitsInWorkRange = collect($workRanges)->contains(
                fn ($range) => $range['start'] <= $newDateTime && $range['end'] >= $slotEnd,
            );

            if (! $fitsInWorkRange) {
                throw new RescheduleConflictException(
                    'Lo slot alle ' . $newDateTime->format('H:i') . ' del ' . $newDateTime->format('d/m/Y') . ' è fuori orario o in un periodo bloccato.',
                    RescheduleConflictException::OUTSIDE_HOURS,
                );
            }

            // 4. Conflitti con altri appuntamenti — esclude self, lockForUpdate su query
            $others = Appointment::where('business_id', $appointment->business_id)
                ->where('staff_id', $appointment->staff_id)
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('scheduled_date', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
                ->lockForUpdate()
                ->get();

            foreach ($others as $other) {
                $otherDur   = $this->durationFor($other);
                $otherStart = $other->scheduled_date;
                $otherEnd   = $other->scheduled_date->copy()->addMinutes($otherDur);

                if ($newDateTime < $otherEnd && $slotEnd > $otherStart) {
                    throw new RescheduleConflictException(
                        'Conflitto con un altro appuntamento alle ' . $otherStart->format('H:i') . '.',
                        RescheduleConflictException::CONFLICT,
                    );
                }
            }

            // 5. Salva
            $appointment->update(['scheduled_date' => $newDateTime]);

            return $appointment->fresh();
        });
    }

    private function durationFor(Appointment $appointment): int
    {
        $serviceIds = $appointment->service_ids
            ?? ($appointment->service_id ? [$appointment->service_id] : []);

        return $this->slots->calculateTotalDuration($serviceIds) ?: 30;
    }
}
```

- [ ] **Step 4: Esegui i test — devono tutti passare**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/AppointmentRescheduleTest.php
```

Expected: `Tests: 13 passed`. Se qualcuno fallisce, leggi il messaggio di errore e correggi il service prima di procedere.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AppointmentRescheduleService.php tests/Feature/AppointmentRescheduleTest.php
git commit -m "feat: implement AppointmentRescheduleService with TDD (13 tests)"
```

---

## Task 4: Widget — integrazione drag-and-drop

**Files:**
- Modifica: `app/Filament/Widgets/AppointmentCalendarWidget.php`

Questo task non ha test automatici propri — la logica di validazione è già coperta da Task 3. Esegui la suite completa alla fine per verificare che nulla sia regredito.

- [ ] **Step 1: Aggiungi 4 import mancanti**

Apri `app/Filament/Widgets/AppointmentCalendarWidget.php`. Alla riga 21 c'è `use Livewire\Attributes\On;`. Aggiungi i 4 import seguenti subito dopo la riga 5 (`use App\Exceptions\BookingException;`), in ordine alfabetico rispetto agli altri:

```php
use App\Exceptions\RescheduleConflictException;
use App\Services\AppointmentRescheduleService;
use Carbon\Carbon;
use Filament\Notifications\Actions\Action as NotificationAction;
```

Il blocco import risultante nelle prime righe sarà:

```php
use App\Exceptions\BookingException;
use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\StaffBlockout;
use App\Models\User;
use App\Services\AppointmentRescheduleService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Livewire\Attributes\On;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
```

- [ ] **Step 2: Aggiungi drag config a `config()`**

Il metodo `config()` (riga 38) termina con `'resourceAreaWidth' => '0px',`. Aggiungi i nuovi tasti prima della parentesi quadra di chiusura:

```php
'editable'              => true,
'eventStartEditable'    => true,
'eventDurationEditable' => false,
'eventResourceEditable' => false,
'views' => [
    'dayGridMonth' => ['editable' => false],
    'listWeek'     => ['editable' => false],
],
```

Il return array completo di `config()` diventa:

```php
return [
    'initialView'   => 'dayGridMonth',
    'headerToolbar' => [
        'left'   => 'prev,next today',
        'center' => 'title',
        'right'  => 'dayGridMonth,timeGridWeek,resourceTimeGridDay,listWeek',
    ],
    'buttonText' => [
        'dayGridMonth'        => 'Mese',
        'timeGridWeek'        => 'Settimana',
        'resourceTimeGridDay' => 'Giorno',
        'listWeek'            => 'Lista',
        'today'               => 'Oggi',
    ],
    'locale'            => 'it',
    'eventDisplay'      => 'block',
    'displayEventTime'  => true,
    'displayEventEnd'   => true,
    'eventTimeFormat'   => ['hour' => '2-digit', 'minute' => '2-digit', 'hour12' => false],
    'dayMaxEvents'      => true,
    'contentHeight'     => 'auto',
    'slotMinTime'       => '07:00:00',
    'slotMaxTime'       => '21:00:00',
    'allDaySlot'        => false,
    'resources'         => $this->getStaffResources(),
    'resourceAreaWidth' => '0px',
    'editable'              => true,
    'eventStartEditable'    => true,
    'eventDurationEditable' => false,
    'eventResourceEditable' => false,
    'views' => [
        'dayGridMonth' => ['editable' => false],
        'listWeek'     => ['editable' => false],
    ],
];
```

- [ ] **Step 3: Aggiungi flag `editable` agli appointment events in `fetchEvents()`**

In `fetchEvents()`, il map degli appointment events (riga ~130) restituisce un array per ciascun appuntamento. L'array attuale termina con:

```php
'extendedProps'   => ['status' => $appointment->status],
```

Aggiungi i 4 flag editable subito dopo quella riga:

```php
'extendedProps'   => ['status' => $appointment->status],
'editable'         => in_array($appointment->status, ['pending', 'confirmed']),
'startEditable'    => in_array($appointment->status, ['pending', 'confirmed']),
'durationEditable' => false,
'resourceEditable' => false,
```

- [ ] **Step 4: Aggiungi flag `editable` ai blockout events in `fetchEvents()`**

Nel map dei blockout events (riga ~153), l'array attuale termina con:

```php
'extendedProps'   => ['type' => 'blockout'],
```

Aggiungi i 4 flag subito dopo:

```php
'extendedProps'   => ['type' => 'blockout'],
'editable'         => false,
'startEditable'    => false,
'durationEditable' => false,
'resourceEditable' => false,
```

- [ ] **Step 5: Aggiungi il metodo `onEventDrop()` dopo `onEventClick()`**

`onEventClick()` inizia alla riga 236. Aggiungi `onEventDrop()` subito dopo la sua chiusura:

```php
public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta): bool
{
    if (str_starts_with((string) ($event['id'] ?? ''), 'blockout-')) {
        return false;
    }

    $user = Filament::auth()->user();

    $appointment = Appointment::where('id', $event['id'])
        ->where('business_id', $user?->business_id)
        ->first();

    if (! $appointment) {
        return false;
    }

    // Guard cambio staff — non supportato in v1
    $eventResourceId = $event['resourceId'] ?? null;
    if ($eventResourceId !== null && (int) $eventResourceId !== (int) $appointment->staff_id) {
        Notification::make()
            ->title('Il cambio operatore tramite drag non è supportato.')
            ->danger()
            ->send();

        return false;
    }

    try {
        app(AppointmentRescheduleService::class)->reschedule(
            $appointment,
            Carbon::parse($event['start']),
            $user,
        );
    } catch (RescheduleConflictException $e) {
        Notification::make()
            ->title($e->getMessage())
            ->danger()
            ->send();

        // return false fa chiamare info.revert() al wrapper JS di saade/filament-fullcalendar v4.
        // Se dopo il drop l'evento non torna alla posizione originale, aggiungere
        // $this->dispatch('filament-fullcalendar--refresh') come fallback prima del return.
        return false;
    }

    Notification::make()
        ->title('Appuntamento spostato alle ' . Carbon::parse($event['start'])->format('H:i'))
        ->success()
        ->actions([
            NotificationAction::make('undo')
                ->label('Annulla')
                ->dispatch('undo-reschedule', [
                    'appointmentId'    => $appointment->id,
                    'previousDateTime' => $oldEvent['start'],
                ]),
        ])
        ->send();

    $this->dispatch('filament-fullcalendar--refresh')->to(AppointmentCalendarWidget::class);

    return true;
}
```

- [ ] **Step 6: Aggiungi il metodo `undoReschedule()` dopo `onEventDrop()`**

```php
#[On('undo-reschedule')]
public function undoReschedule(int $appointmentId, string $previousDateTime): void
{
    $user = Filament::auth()->user();

    $appointment = Appointment::where('id', $appointmentId)
        ->where('business_id', $user?->business_id)
        ->first();

    if (! $appointment) {
        Notification::make()
            ->title('Appuntamento non trovato.')
            ->danger()
            ->send();

        return;
    }

    try {
        app(AppointmentRescheduleService::class)->reschedule(
            $appointment,
            Carbon::parse($previousDateTime),
            $user,
        );

        Notification::make()
            ->title('Spostamento annullato.')
            ->success()
            ->send();
    } catch (RescheduleConflictException $e) {
        Notification::make()
            ->title('Non è più possibile ripristinare l\'orario precedente.')
            ->body($e->getMessage())
            ->danger()
            ->send();
    }

    $this->dispatch('filament-fullcalendar--refresh')->to(AppointmentCalendarWidget::class);
}
```

- [ ] **Step 7: Esegui la suite completa per verificare nessuna regressione**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: tutti i test passano (inclusi i 13 di `AppointmentRescheduleTest` e i test esistenti di `SlotBlockingTest`, `WalkInTest`, ecc.). Se `SlotCalculationServiceTest` mostra 16 fallimenti legati a FK su `system_settings` — questo è un pre-existing failure non correlato a questa feature; ignoralo e verifica solo che non compaiano nuovi fallimenti.

- [ ] **Step 8: Commit**

```bash
git add app/Filament/Widgets/AppointmentCalendarWidget.php
git commit -m "feat: add drag-and-drop reschedule to calendar widget"
```

---

## Verifica manuale post-implementazione

Dopo aver completato tutti e 4 i task:

1. Avvia i servizi: `docker-compose up -d`
2. Apri il calendario in `timeGridWeek` o `resourceTimeGridDay`
3. Trascina un appuntamento `pending` o `confirmed` a un nuovo orario — deve apparire il toast di successo con il bottone "Annulla"
4. Clicca "Annulla" nel toast — l'appuntamento deve tornare all'orario originale
5. Trascina un appuntamento in uno slot fuori orario lavorativo — l'evento deve tornare alla posizione originale e apparire un toast di errore
6. Verifica che gli appuntamenti `completed` e `cancelled` non siano trascinabili
7. Verifica che i blockout (sfondo rosso) non siano trascinabili
8. Verifica che la vista `dayGridMonth` e `listWeek` non permettano il drag
