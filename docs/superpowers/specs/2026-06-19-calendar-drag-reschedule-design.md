# Calendar Drag-and-Drop Reschedule — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Staff e admin possono trascinare un appuntamento nella vista calendario per spostarlo a un nuovo orario, con validazione lato server tramite la logica esistente di disponibilità.

**Architecture:** Un nuovo `AppointmentRescheduleService` centralizza validazione e salvataggio; il widget Filament espone `onEventDrop` e `undoReschedule` che delegano al service. Il frontend usa i flag nativi di FullCalendar per controllare quali eventi e viste supportano il drag.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, `saade/filament-fullcalendar` v4 beta, FullCalendar v6, Alpine.js, `SlotCalculationService` esistente.

---

## Scope

**In scope:**
- Drag temporale (stesso staff) in `timeGridWeek` e `resourceTimeGridDay`
- Solo appuntamenti `pending` e `confirmed`
- Validazione: fasce lavorative, blockout staff, conflitti con altri appuntamenti (escluso self)
- Salvataggio immediato + undo toast (toast scompare dopo ~8 s)
- Undo passa dalla stessa validazione del reschedule

**Out of scope (v1):**
- Drag tra staff diversi (cambio risorsa)
- Resize della durata
- Drag nella vista `dayGridMonth` e `listWeek`

---

## File

**Nuovi:**
- `app/Services/AppointmentRescheduleService.php`
- `app/Exceptions/RescheduleConflictException.php`
- `tests/Feature/AppointmentRescheduleTest.php`
- Migration: aggiunta indice `[business_id, staff_id, scheduled_date, status]` su `appointments`

**Modificati:**
- `app/Filament/Widgets/AppointmentCalendarWidget.php`

---

## Sezione 1 — Architettura

```
onEventDrop() [Widget]
    │
    ▼
AppointmentRescheduleService::reschedule()
    │
    ├─ SlotCalculationService::getWorkRangesForOperator()  (fasce + blockout)
    ├─ Query conflitti diretta (esclude self, lockForUpdate)
    │
    ├─ OK → salva Appointment.scheduled_date → return Appointment
    │        → Notification con action "Annulla" → undoReschedule()
    │
    └─ RescheduleConflictException → revert FullCalendar + toast danger
```

---

## Sezione 2 — `RescheduleConflictException`

```php
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

---

## Sezione 3 — `AppointmentRescheduleService`

```php
namespace App\Services;

use App\Exceptions\RescheduleConflictException;
use App\Models\Appointment;
use App\Models\Service;
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
            // Ricarica con lock per serializzare modifiche concorrenti allo stesso appuntamento
            $appointment = Appointment::where('id', $appointment->id)
                ->lockForUpdate()
                ->firstOrFail();

            // 1. Permessi
            $canManageAny = $actor->isAdmin()
                || $actor->can('appointments.view_all');

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

            $workRanges = $this->slots->getWorkRangesForOperator(
                $appointment->staff,
                $date,
            );

            $fitsInWorkRange = collect($workRanges)->contains(
                fn ($range) => $range['start'] <= $newDateTime && $range['end'] >= $slotEnd,
            );

            if (! $fitsInWorkRange) {
                throw new RescheduleConflictException(
                    'Lo slot alle ' . $newDateTime->format('H:i') . ' del ' . $newDateTime->format('d/m/Y') . ' è fuori orario o in un periodo bloccato.',
                    RescheduleConflictException::OUTSIDE_HOURS,
                );
            }

            // 4. Conflitti con altri appuntamenti (esclude self, lockForUpdate)
            $dayStart = $date->copy()->startOfDay();
            $dayEnd   = $date->copy()->endOfDay();

            $others = Appointment::where('business_id', $appointment->business_id)
                ->where('staff_id', $appointment->staff_id)
                ->where('id', '!=', $appointment->id)
                ->whereIn('status', ['pending', 'confirmed'])
                ->whereBetween('scheduled_date', [$dayStart, $dayEnd])
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

        // calculateTotalDuration() è public in SlotCalculationService
        return $this->slots->calculateTotalDuration($serviceIds) ?: 30;
    }
}
```

**Nota sulla race condition:** La transazione + `lockForUpdate` sull'appuntamento e sulla query dei potenziali conflitti riduce le race condition, serializzando modifiche concorrenti agli stessi record. Non elimina tutti i conflitti a livello di intervallo: una prenotazione nuova su uno slot adiacente può sfuggire al lock se inserisce righe non ancora esistenti. L'indice su `[business_id, staff_id, scheduled_date, status]` migliora coerenza e performance delle query di lock.

**Nota su `calculateTotalDuration()`:** Il metodo è già `public` in `SlotCalculationService` — nessuna modifica necessaria.

---

## Sezione 4 — Widget: `onEventDrop`, `undoReschedule`, flag per evento

### Import aggiuntivi

```php
use App\Exceptions\RescheduleConflictException;
use App\Services\AppointmentRescheduleService;
use Filament\Notifications\Actions\Action as NotificationAction;
```

### `onEventDrop`

```php
public function onEventDrop(array $event, array $oldEvent, array $relatedEvents, array $delta): bool
{
    // Blockout: non draggabili (già bloccati via flag evento, guard per sicurezza)
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

    // Guard cambio staff (v1: non supportato)
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

        // return false dovrebbe far chiamare info.revert() al wrapper JS di saade/filament-fullcalendar v4.
        // Verificare al primo run: se l'evento non torna visivamente alla posizione originale,
        // aggiungere un dispatch('filament-fullcalendar--refresh') come fallback.
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

### `undoReschedule`

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

### Flag per evento in `fetchEvents()`

Appointment events — aggiungere a ciascun array evento:

```php
'editable'         => in_array($appointment->status, ['pending', 'confirmed']),
'startEditable'    => in_array($appointment->status, ['pending', 'confirmed']),
'durationEditable' => false,
'resourceEditable' => false,
```

Blockout events:

```php
'editable'         => false,
'startEditable'    => false,
'durationEditable' => false,
'resourceEditable' => false,
```

### Aggiunte a `config()`

```php
'editable'              => true,   // FullCalendar non abilita il drag di default
'eventStartEditable'    => true,
'eventDurationEditable' => false,
'eventResourceEditable' => false,
'views' => [
    // Viste reali: dayGridMonth, timeGridWeek, resourceTimeGridDay, listWeek
    'dayGridMonth' => ['editable' => false],
    'listWeek'     => ['editable' => false],
    // timeGridWeek e resourceTimeGridDay ereditano editable: true
],
```

---

## Sezione 5 — Test: `AppointmentRescheduleTest.php`

Tutti i test coprono `AppointmentRescheduleService` direttamente (nessun widget).

### Setup

```php
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
```

### Helper per reason code (evita doppia chiamata)

```php
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
```

### Fixture helper

```php
function makeStaffWithService(): array
{
    $business = app('current_business_id');
    $staff = User::factory()->create(['business_id' => $business]);
    $staff->assignRole('staff');

    $service = Service::factory()->create([
        'business_id'      => $business,
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
```

### Test 1: pending → spostato con successo

```php
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
```

### Test 2: confirmed → spostato con successo

```php
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
```

### Test 3: admin può spostare l'appuntamento di qualsiasi staff

```php
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
```

### Test 4: staff con `appointments.view_all` può spostare appuntamenti altrui

```php
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
```

### Test 5: staff normale non può spostare l'appuntamento di un altro → `FORBIDDEN`

```php
it('throws FORBIDDEN when staff tries to reschedule another staff appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalDate = $appointment->scheduled_date->format('H:i');

    $otherStaff = User::factory()->create(['business_id' => app('current_business_id')]);
    $otherStaff->assignRole('staff');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $otherStaff,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```

### Test 6: business mismatch → `FORBIDDEN`

```php
it('throws FORBIDDEN when actor belongs to a different business', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalDate = $appointment->scheduled_date->format('H:i');

    $otherBusiness = Business::factory()->create();
    $actor = User::factory()->create(['business_id' => $otherBusiness->id]);
    $actor->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $actor,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```

### Test 7: status `completed` → `WRONG_STATUS`

```php
it('throws WRONG_STATUS when rescheduling a completed appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'completed');
    $originalDate = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```

### Test 8: slot fuori orario lavorativo → `OUTSIDE_HOURS`

```php
it('throws OUTSIDE_HOURS when slot is outside working hours', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);
    $originalDate = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 08:00'), // prima delle 09:00
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```

### Test 9: slot in un blockout → `OUTSIDE_HOURS`

```php
it('throws OUTSIDE_HOURS when slot falls within a staff blockout', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service);

    StaffBlockout::factory()->create([
        'user_id'    => $staff->id,
        'business_id' => app('current_business_id'),
        'start_date' => '2026-06-23',
        'end_date'   => '2026-06-23',
        'start_time' => '13:00',
        'end_time'   => '14:00',
    ]);

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 13:00'),
        $staff,
        RescheduleConflictException::OUTSIDE_HOURS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});
```

### Test 10: conflitto con altro appuntamento → `CONFLICT`

```php
it('throws CONFLICT when slot overlaps another appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Secondo appuntamento alle 11:00 (60 min → 11:00-12:00)
    makeAppointment($staff, $service, 'confirmed', '11:00');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 11:30'), // overlap con 11:00-12:00
        $staff,
        RescheduleConflictException::CONFLICT,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe('10:00');
});
```

### Test 11: nessun auto-conflitto — l'appuntamento non si blocca da solo

```php
it('does not conflict with itself when moved to an overlapping position', function () {
    [$staff, $service] = makeStaffWithService();
    // Appuntamento alle 10:00, 60 min → occupa 10:00-11:00
    $appointment = makeAppointment($staff, $service, 'pending', '10:00');

    // Spostiamo alle 10:30 → 10:30-11:30
    // Senza auto-esclusione questo fallirebbe perché 10:00-11:00 è "occupato"
    $result = app(AppointmentRescheduleService::class)->reschedule(
        $appointment,
        Carbon::parse('2026-06-23 10:30'),
        $staff,
    );

    expect($result->scheduled_date->format('H:i'))->toBe('10:30');
});
```

### Test 12: appuntamento di altro business → `FORBIDDEN`, scheduled_date invariata

```php
it('throws FORBIDDEN when appointment belongs to a different business than actor', function () {
    [$staff, $service] = makeStaffWithService();

    // Appuntamento creato su un secondo business, non quello dell'actor
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
    $originalDate = $appointment->scheduled_date->format('H:i');

    // Actor è admin del business principale, non di $otherBusiness
    $admin = User::factory()->create(['business_id' => app('current_business_id')]);
    $admin->assignRole('admin');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $admin,
        RescheduleConflictException::FORBIDDEN,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```

### Test 13: status `cancelled` → `WRONG_STATUS`, scheduled_date invariata

```php
it('throws WRONG_STATUS when rescheduling a cancelled appointment', function () {
    [$staff, $service] = makeStaffWithService();
    $appointment = makeAppointment($staff, $service, 'cancelled');
    $originalDate = $appointment->scheduled_date->format('H:i');

    expectRescheduleReason(
        $appointment,
        Carbon::parse('2026-06-23 14:00'),
        $staff,
        RescheduleConflictException::WRONG_STATUS,
    );

    expect($appointment->fresh()->scheduled_date->format('H:i'))->toBe($originalDate);
});
```
