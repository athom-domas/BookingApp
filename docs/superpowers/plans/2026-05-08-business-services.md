# Business Logic Services – Phase 4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement three services — SlotGeneratorService, AppointmentService, PaymentService — that encapsulate all booking business logic, separated from Filament presentation.

**Architecture:** Each service is a plain PHP class in `app/Services/`. `AppointmentService` depends on no other service. `SlotGeneratorService` is independent. `PaymentService` receives a `\Stripe\StripeClient` via constructor injection, bound in `AppServiceProvider`. A single `BookingException` covers all domain errors. All tests hit a real DB (Feature tests + RefreshDatabase) except Stripe calls, which are mocked via Mockery.

**Tech Stack:** Laravel 13, PHP 8.4, Stripe PHP SDK v20, Mockery, Pest 4, MySQL 8 (via Docker: `docker-compose run --rm app <cmd>`).

---

## Domain context

- `AvailabilityRule`: `user_id`, `day_of_week` (0=Sun, 6=Sat), `start_time`, `end_time`, `is_available`
- `TimeSlot`: `user_id`, `date`, `start_time`, `end_time`, `is_available`, `appointment_id` (nullable FK)
- `Appointment`: `user_id`, `service_id`, `staff_id`, `scheduled_date` (datetime), `status` (pending/confirmed/completed/cancelled), `final_price`, `notes`
- `AppointmentReminder`: `appointment_id`, `type` (email/sms), `scheduled_for`, `sent_at`, `status` (pending/sent/failed), `error_message`
- `Payment`: `appointment_id`, `user_id`, `amount`, `status` (pending/completed/refunded/failed), `stripe_transaction_id`, `stripe_response` (json)
- `Service`: `duration_minutes`, `price`, `active`

## File map

**Create:**
```
app/Exceptions/BookingException.php
app/Services/SlotGeneratorService.php
app/Services/AppointmentService.php
app/Services/PaymentService.php
tests/Feature/Services/SlotGeneratorServiceTest.php
tests/Feature/Services/AppointmentServiceTest.php
tests/Feature/Services/PaymentServiceTest.php
```

**Modify:**
```
app/Providers/AppServiceProvider.php  — bind PaymentService with StripeClient
```

---

### Task 1: BookingException + SlotGeneratorService

**Files:**
- Create: `app/Exceptions/BookingException.php`
- Create: `app/Services/SlotGeneratorService.php`
- Create: `tests/Feature/Services/SlotGeneratorServiceTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Services/SlotGeneratorServiceTest.php`:
```php
<?php

use App\Models\AvailabilityRule;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\SlotGeneratorService;
use Carbon\Carbon;

it('generates slots for a day with an availability rule', function () {
    $staff = User::factory()->create();
    // Monday = 1 in Carbon
    $weekStart = Carbon::now()->startOfWeek(); // Monday
    AvailabilityRule::factory()->create([
        'user_id'    => $staff->id,
        'day_of_week' => 1,
        'start_time' => '09:00:00',
        'end_time'   => '11:00:00',
        'is_available' => true,
    ]);

    $service = app(SlotGeneratorService::class);
    $count = $service->generateWeeklySlots($staff->id, $weekStart, 60);

    expect($count)->toBe(2);
    expect(TimeSlot::where('user_id', $staff->id)->count())->toBe(2);
});

it('skips days without availability rules', function () {
    $staff = User::factory()->create();
    $weekStart = Carbon::now()->startOfWeek();

    $count = app(SlotGeneratorService::class)->generateWeeklySlots($staff->id, $weekStart, 30);

    expect($count)->toBe(0);
});

it('skips slots conflicting with existing appointments', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $weekStart = Carbon::now()->startOfWeek(); // Monday
    $monday  = $weekStart->copy();

    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '11:00:00',
        'is_available' => true,
    ]);

    // Appointment at 09:00, duration 60 + 15 buffer = blocks until 10:15
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_id'     => $service->id,
        'scheduled_date' => $monday->copy()->setTime(9, 0),
        'status'         => 'pending',
    ]);

    // 30-min slots: 09:00, 09:30, 10:00 conflict; 10:30 free
    $count = app(SlotGeneratorService::class)->generateWeeklySlots($staff->id, $weekStart, 30);

    expect($count)->toBe(1);
    expect(TimeSlot::where('user_id', $staff->id)->first()->start_time)->toBe('10:30:00');
});

it('is idempotent when called twice', function () {
    $staff = User::factory()->create();
    $weekStart = Carbon::now()->startOfWeek();
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '10:00:00',
        'is_available' => true,
    ]);

    $svc = app(SlotGeneratorService::class);
    $svc->generateWeeklySlots($staff->id, $weekStart, 60);
    $second = $svc->generateWeeklySlots($staff->id, $weekStart, 60);

    expect($second)->toBe(0);
    expect(TimeSlot::where('user_id', $staff->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/SlotGeneratorServiceTest.php
```

Expected: FAIL — `App\Services\SlotGeneratorService` not found.

- [ ] **Step 3: Create BookingException**

Create `app/Exceptions/BookingException.php`:
```php
<?php

namespace App\Exceptions;

class BookingException extends \RuntimeException {}
```

- [ ] **Step 4: Create SlotGeneratorService**

Create `app/Services/SlotGeneratorService.php`:
```php
<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\TimeSlot;
use Carbon\Carbon;

class SlotGeneratorService
{
    public function generateWeeklySlots(int $staffId, Carbon $weekStart, int $slotMinutes = 30): int
    {
        $created = 0;

        for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
            $date      = $weekStart->copy()->addDays($dayOffset);
            $dayOfWeek = (int) $date->dayOfWeek;

            $rule = AvailabilityRule::where('user_id', $staffId)
                ->where('day_of_week', $dayOfWeek)
                ->where('is_available', true)
                ->first();

            if (! $rule) {
                continue;
            }

            $blockedWindows = $this->getBlockedWindows($staffId, $date);
            $created += $this->generateDaySlots($staffId, $date, $rule, $slotMinutes, $blockedWindows);
        }

        return $created;
    }

    private function getBlockedWindows(int $staffId, Carbon $date): array
    {
        return Appointment::where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('scheduled_date', $date->format('Y-m-d'))
            ->with('service')
            ->get()
            ->map(function (Appointment $appt): array {
                $start = Carbon::parse($appt->scheduled_date);
                $end   = $start->copy()->addMinutes($appt->service->duration_minutes + 15);
                return ['start' => $start, 'end' => $end];
            })
            ->all();
    }

    private function generateDaySlots(int $staffId, Carbon $date, AvailabilityRule $rule, int $slotMinutes, array $blockedWindows): int
    {
        $created    = 0;
        $slotStart  = Carbon::parse($date->format('Y-m-d') . ' ' . $rule->start_time);
        $windowEnd  = Carbon::parse($date->format('Y-m-d') . ' ' . $rule->end_time);

        while ($slotStart->copy()->addMinutes($slotMinutes)->lte($windowEnd)) {
            $slotEnd = $slotStart->copy()->addMinutes($slotMinutes);

            if (! $this->overlapsAny($slotStart, $slotEnd, $blockedWindows)) {
                $slot = TimeSlot::firstOrCreate(
                    [
                        'user_id'    => $staffId,
                        'date'       => $date->format('Y-m-d'),
                        'start_time' => $slotStart->format('H:i:s'),
                        'end_time'   => $slotEnd->format('H:i:s'),
                    ],
                    ['is_available' => true]
                );

                if ($slot->wasRecentlyCreated) {
                    $created++;
                }
            }

            $slotStart->addMinutes($slotMinutes);
        }

        return $created;
    }

    private function overlapsAny(Carbon $slotStart, Carbon $slotEnd, array $blockedWindows): bool
    {
        foreach ($blockedWindows as $window) {
            if ($slotStart->lt($window['end']) && $slotEnd->gt($window['start'])) {
                return true;
            }
        }

        return false;
    }
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/SlotGeneratorServiceTest.php
```

Expected: PASS — 4 tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Exceptions/BookingException.php app/Services/SlotGeneratorService.php tests/Feature/Services/SlotGeneratorServiceTest.php
git commit -m "feat: add SlotGeneratorService with weekly slot generation"
```

---

### Task 2: AppointmentService

**Files:**
- Create: `app/Services/AppointmentService.php`
- Create: `tests/Feature/Services/AppointmentServiceTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Services/AppointmentServiceTest.php`:
```php
<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;

// ── validateAvailability ──────────────────────────────────────────────────────

it('validateAvailability returns false when no rule exists for that day', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    // No AvailabilityRule created
    $monday = Carbon::now()->startOfWeek()->setTime(10, 0);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday))->toBeFalse();
});

it('validateAvailability returns false when time is outside rule window', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $monday  = Carbon::now()->startOfWeek();
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(8, 0)))->toBeFalse();
    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(18, 0)))->toBeFalse();
});

it('validateAvailability returns true when slot is free', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $monday  = Carbon::now()->startOfWeek();
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);

    expect(app(AppointmentService::class)->validateAvailability($staff->id, $service->id, $monday->copy()->setTime(10, 0)))->toBeTrue();
});

it('validateAvailability returns false when appointment conflicts', function () {
    $staff          = User::factory()->create();
    $existingService = Service::factory()->create(['duration_minutes' => 60]);
    $newService     = Service::factory()->create(['duration_minutes' => 30]);
    $monday         = Carbon::now()->startOfWeek();
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);
    // Existing appointment 10:00–11:00 + 15 buffer = blocks until 11:15
    Appointment::factory()->create([
        'staff_id'       => $staff->id,
        'service_id'     => $existingService->id,
        'scheduled_date' => $monday->copy()->setTime(10, 0),
        'status'         => 'pending',
    ]);

    // New appointment at 10:30 (30 min) overlaps with 10:00–11:15
    expect(app(AppointmentService::class)->validateAvailability($staff->id, $newService->id, $monday->copy()->setTime(10, 30)))->toBeFalse();
});

// ── bookAppointment ───────────────────────────────────────────────────────────

it('bookAppointment creates appointment with correct attributes', function () {
    $user    = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 50.00]);
    $staff   = User::factory()->create();
    $monday  = Carbon::now()->startOfWeek()->addDays(7)->setTime(10, 0); // next Monday
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    expect($appointment->status)->toBe('pending');
    expect((float) $appointment->final_price)->toBe(50.00);
    expect($appointment->user_id)->toBe($user->id);
    expect($appointment->staff_id)->toBe($staff->id);
});

it('bookAppointment creates a 24h reminder', function () {
    $user    = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30, 'price' => 30.00]);
    $staff   = User::factory()->create();
    $monday  = Carbon::now()->startOfWeek()->addDays(7)->setTime(10, 0);
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    $reminder = AppointmentReminder::where('appointment_id', $appointment->id)->first();
    expect($reminder)->not->toBeNull();
    expect($reminder->type)->toBe('email');
    expect($reminder->status)->toBe('pending');
    expect(Carbon::parse($reminder->scheduled_for)->format('Y-m-d H:i'))->toBe($monday->copy()->subDay()->format('Y-m-d H:i'));
});

it('bookAppointment marks existing time slot as unavailable', function () {
    $user    = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60, 'price' => 40.00]);
    $staff   = User::factory()->create();
    $monday  = Carbon::now()->startOfWeek()->addDays(7)->setTime(10, 0);
    AvailabilityRule::factory()->create([
        'user_id'     => $staff->id,
        'day_of_week' => 1,
        'start_time'  => '09:00:00',
        'end_time'    => '17:00:00',
        'is_available' => true,
    ]);
    $slot = TimeSlot::factory()->create([
        'user_id'    => $staff->id,
        'date'       => $monday->format('Y-m-d'),
        'start_time' => '10:00:00',
        'end_time'   => '11:00:00',
        'is_available' => true,
    ]);

    $appointment = app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday);

    $slot->refresh();
    expect($slot->is_available)->toBeFalse();
    expect($slot->appointment_id)->toBe($appointment->id);
});

it('bookAppointment throws BookingException when staff is unavailable', function () {
    $user    = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 30]);
    $staff   = User::factory()->create();
    // No AvailabilityRule — staff unavailable
    $monday = Carbon::now()->startOfWeek()->addDays(7)->setTime(10, 0);

    expect(fn () => app(AppointmentService::class)->bookAppointment($user->id, $service->id, $staff->id, $monday))
        ->toThrow(BookingException::class);
});

// ── cancelAppointment ─────────────────────────────────────────────────────────

it('cancelAppointment updates status to cancelled', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status'         => 'pending',
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    expect($appointment->fresh()->status)->toBe('cancelled');
});

it('cancelAppointment frees the linked time slot', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status'         => 'pending',
    ]);
    $slot = TimeSlot::factory()->create([
        'user_id'        => $appointment->staff_id,
        'date'           => now()->addDays(3)->format('Y-m-d'),
        'is_available'   => false,
        'appointment_id' => $appointment->id,
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id);

    $slot->refresh();
    expect($slot->is_available)->toBeTrue();
    expect($slot->appointment_id)->toBeNull();
});

it('cancelAppointment creates a cancellation reminder', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status'         => 'pending',
    ]);

    app(AppointmentService::class)->cancelAppointment($appointment->id, 'Cliente assente');

    expect(AppointmentReminder::where('appointment_id', $appointment->id)->exists())->toBeTrue();
});

it('cancelAppointment throws BookingException within 24h', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addHours(12),
        'status'         => 'pending',
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment->id))
        ->toThrow(BookingException::class);
});

it('cancelAppointment throws BookingException for completed appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDays(3),
        'status'         => 'completed',
    ]);

    expect(fn () => app(AppointmentService::class)->cancelAppointment($appointment->id))
        ->toThrow(BookingException::class);
});

// ── getAvailableSlots ─────────────────────────────────────────────────────────

it('getAvailableSlots returns only slots that fit the service duration', function () {
    $staff   = User::factory()->create();
    $service = Service::factory()->create(['duration_minutes' => 60]);
    $date    = now()->addDays(3)->format('Y-m-d');

    // 60-min slot: fits
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'is_available' => true]);
    // 30-min slot: too short
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '10:00:00', 'end_time' => '10:30:00', 'is_available' => true]);
    // 60-min slot but unavailable
    TimeSlot::factory()->create(['user_id' => $staff->id, 'date' => $date, 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'is_available' => false]);

    $slots = app(AppointmentService::class)->getAvailableSlots($service->id, $staff->id, $date);

    expect($slots)->toHaveCount(1);
    expect($slots[0]['start_time'])->toBe('09:00:00');
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/AppointmentServiceTest.php
```

Expected: FAIL — `App\Services\AppointmentService` not found.

- [ ] **Step 3: Create AppointmentService**

Create `app/Services/AppointmentService.php`:
```php
<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\AvailabilityRule;
use App\Models\Service;
use App\Models\TimeSlot;
use Carbon\Carbon;

class AppointmentService
{
    public function validateAvailability(int $staffId, int $serviceId, Carbon $dateTime): bool
    {
        $rule = AvailabilityRule::where('user_id', $staffId)
            ->where('day_of_week', (int) $dateTime->dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (! $rule) {
            return false;
        }

        $ruleStart = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->start_time);
        $ruleEnd   = Carbon::parse($dateTime->format('Y-m-d') . ' ' . $rule->end_time);

        if ($dateTime->lt($ruleStart) || $dateTime->gte($ruleEnd)) {
            return false;
        }

        $service    = Service::findOrFail($serviceId);
        $newApptEnd = $dateTime->copy()->addMinutes($service->duration_minutes + 15);

        $conflicts = Appointment::where('staff_id', $staffId)
            ->whereNotIn('status', ['cancelled'])
            ->whereDate('scheduled_date', $dateTime->format('Y-m-d'))
            ->with('service')
            ->get();

        foreach ($conflicts as $existing) {
            $existingStart = Carbon::parse($existing->scheduled_date);
            $existingEnd   = $existingStart->copy()->addMinutes($existing->service->duration_minutes + 15);

            if ($dateTime->lt($existingEnd) && $newApptEnd->gt($existingStart)) {
                return false;
            }
        }

        return true;
    }

    public function bookAppointment(int $userId, int $serviceId, int $staffId, Carbon $scheduledDate): Appointment
    {
        if (! $this->validateAvailability($staffId, $serviceId, $scheduledDate)) {
            throw new BookingException('Staff non disponibile per questa data e ora.');
        }

        $service     = Service::findOrFail($serviceId);
        $appointment = Appointment::create([
            'user_id'        => $userId,
            'service_id'     => $serviceId,
            'staff_id'       => $staffId,
            'scheduled_date' => $scheduledDate,
            'status'         => 'pending',
            'final_price'    => $service->price,
        ]);

        $slot = TimeSlot::where('user_id', $staffId)
            ->where('date', $scheduledDate->format('Y-m-d'))
            ->where('start_time', $scheduledDate->format('H:i:s'))
            ->where('is_available', true)
            ->first();

        if ($slot) {
            $slot->update(['is_available' => false, 'appointment_id' => $appointment->id]);
        } else {
            TimeSlot::create([
                'user_id'        => $staffId,
                'date'           => $scheduledDate->format('Y-m-d'),
                'start_time'     => $scheduledDate->format('H:i:s'),
                'end_time'       => $scheduledDate->copy()->addMinutes($service->duration_minutes)->format('H:i:s'),
                'is_available'   => false,
                'appointment_id' => $appointment->id,
            ]);
        }

        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'type'           => 'email',
            'scheduled_for'  => $scheduledDate->copy()->subDay(),
            'status'         => 'pending',
        ]);

        return $appointment;
    }

    public function cancelAppointment(int $appointmentId, ?string $reason = null): void
    {
        $appointment = Appointment::findOrFail($appointmentId);

        if (! $appointment->canBeCancelled()) {
            throw new BookingException('Prenotazione non può essere cancellata.');
        }

        if (now()->diffInHours($appointment->scheduled_date, false) < 24) {
            throw new BookingException('Impossibile cancellare meno di 24 ore prima.');
        }

        $appointment->update([
            'status' => 'cancelled',
            'notes'  => $reason ?? $appointment->notes,
        ]);

        TimeSlot::where('appointment_id', $appointment->id)
            ->update(['is_available' => true, 'appointment_id' => null]);

        AppointmentReminder::create([
            'appointment_id' => $appointment->id,
            'type'           => 'email',
            'scheduled_for'  => now(),
            'status'         => 'pending',
        ]);
    }

    public function getAvailableSlots(int $serviceId, int $staffId, string $date): array
    {
        $service = Service::findOrFail($serviceId);

        return TimeSlot::where('user_id', $staffId)
            ->where('date', $date)
            ->where('is_available', true)
            ->get()
            ->filter(function (TimeSlot $slot) use ($service): bool {
                $start = Carbon::parse($slot->start_time);
                $end   = Carbon::parse($slot->end_time);
                return $start->diffInMinutes($end) >= $service->duration_minutes;
            })
            ->values()
            ->map(fn (TimeSlot $slot): array => [
                'start_time' => $slot->start_time,
                'end_time'   => $slot->end_time,
            ])
            ->all();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/AppointmentServiceTest.php
```

Expected: PASS — 13 tests passed.

- [ ] **Step 5: Commit**

```bash
git add app/Services/AppointmentService.php tests/Feature/Services/AppointmentServiceTest.php
git commit -m "feat: add AppointmentService with booking, validation, and cancellation"
```

---

### Task 3: PaymentService

**Files:**
- Create: `app/Services/PaymentService.php`
- Create: `tests/Feature/Services/PaymentServiceTest.php`
- Modify: `app/Providers/AppServiceProvider.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Services/PaymentServiceTest.php`:
```php
<?php

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use App\Services\PaymentService;
use Mockery\MockInterface;
use Stripe\PaymentIntent;
use Stripe\Refund;

function makePaymentService(MockInterface $mockStripe): PaymentService
{
    return new PaymentService($mockStripe);
}

it('initiateStripePayment creates a pending payment record', function () {
    $appointment = Appointment::factory()->create();

    $fakeIntent = PaymentIntent::constructFrom([
        'id'       => 'pi_test_123',
        'object'   => 'payment_intent',
        'amount'   => 5000,
        'currency' => 'eur',
        'status'   => 'requires_payment_method',
    ]);

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')->once()->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('__get')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $payment = makePaymentService($mockStripe)->initiateStripePayment($appointment->id, 5000);

    expect($payment->status)->toBe('pending');
    expect($payment->stripe_transaction_id)->toBe('pi_test_123');
    expect((float) $payment->amount)->toBe(50.00);
    expect($payment->appointment_id)->toBe($appointment->id);
});

it('handleStripeWebhook marks payment as completed on succeeded event', function () {
    $payment = Payment::factory()->create(['stripe_transaction_id' => 'pi_test_456', 'status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_test_456']],
    ]);

    expect($payment->fresh()->status)->toBe('completed');
});

it('handleStripeWebhook marks payment as failed on failed event', function () {
    $payment = Payment::factory()->create(['stripe_transaction_id' => 'pi_test_789', 'status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.payment_failed',
        'data' => ['object' => ['id' => 'pi_test_789']],
    ]);

    expect($payment->fresh()->status)->toBe('failed');
});

it('handleStripeWebhook ignores unknown transaction IDs without error', function () {
    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);

    expect(fn () => makePaymentService($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_unknown']],
    ]))->not->toThrow(\Throwable::class);
});

it('refundPayment updates status to refunded', function () {
    $payment = Payment::factory()->create([
        'status'               => 'completed',
        'stripe_transaction_id' => 'pi_test_refund',
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'             => 're_test_123',
        'payment_intent' => 'pi_test_refund',
        'status'         => 'succeeded',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')->once()->andReturn($fakeRefund);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('__get')->with('refunds')->andReturn($mockRefunds);

    $result = makePaymentService($mockStripe)->refundPayment($payment->id);

    expect($result->status)->toBe('refunded');
});

it('refundPayment throws BookingException if payment is not completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);

    expect(fn () => makePaymentService($mockStripe)->refundPayment($payment->id))
        ->toThrow(BookingException::class);
});
```

- [ ] **Step 2: Check PaymentFactory exists with required fields**

The `PaymentFactory` must support `stripe_transaction_id`. Read `database/factories/PaymentFactory.php`:
```php
// It should already have: appointment_id, user_id, amount, status, stripe_transaction_id, stripe_response
// If stripe_transaction_id is missing from the definition, add it:
'stripe_transaction_id' => null,
```

Open `database/factories/PaymentFactory.php` and verify. If `stripe_transaction_id` is absent from `definition()`, add `'stripe_transaction_id' => null,` to the array.

- [ ] **Step 3: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Expected: FAIL — `App\Services\PaymentService` not found.

- [ ] **Step 4: Create PaymentService**

Create `app/Services/PaymentService.php`:
```php
<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Appointment;
use App\Models\Payment;
use Stripe\StripeClient;

class PaymentService
{
    public function __construct(private readonly StripeClient $stripe) {}

    public function initiateStripePayment(int $appointmentId, int $amountCents): Payment
    {
        $appointment = Appointment::findOrFail($appointmentId);

        $paymentIntent = $this->stripe->paymentIntents->create([
            'amount'   => $amountCents,
            'currency' => 'eur',
            'metadata' => ['appointment_id' => $appointmentId],
        ]);

        return Payment::create([
            'appointment_id'        => $appointmentId,
            'user_id'               => $appointment->user_id,
            'amount'                => $amountCents / 100,
            'status'                => 'pending',
            'stripe_transaction_id' => $paymentIntent->id,
            'stripe_response'       => $paymentIntent->toArray(),
        ]);
    }

    public function handleStripeWebhook(array $payload): void
    {
        $type          = $payload['type'] ?? '';
        $transactionId = $payload['data']['object']['id'] ?? null;

        if (! $transactionId) {
            return;
        }

        $payment = Payment::where('stripe_transaction_id', $transactionId)->first();

        if (! $payment) {
            return;
        }

        match ($type) {
            'payment_intent.succeeded'      => $payment->update(['status' => 'completed']),
            'payment_intent.payment_failed' => $payment->update(['status' => 'failed']),
            default                         => null,
        };
    }

    public function refundPayment(int $paymentId): Payment
    {
        $payment = Payment::findOrFail($paymentId);

        if ($payment->status !== 'completed') {
            throw new BookingException('Solo i pagamenti completati possono essere rimborsati.');
        }

        $refund = $this->stripe->refunds->create([
            'payment_intent' => $payment->stripe_transaction_id,
        ]);

        $payment->update([
            'status'          => 'refunded',
            'stripe_response' => $refund->toArray(),
        ]);

        return $payment->fresh();
    }
}
```

- [ ] **Step 5: Register PaymentService in AppServiceProvider**

Edit `app/Providers/AppServiceProvider.php`:
```php
<?php

namespace App\Providers;

use App\Services\PaymentService;
use Illuminate\Support\ServiceProvider;
use Stripe\StripeClient;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentService::class, function () {
            return new PaymentService(new StripeClient(config('services.stripe.secret')));
        });
    }

    public function boot(): void {}
}
```

- [ ] **Step 6: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Expected: PASS — 6 tests passed.

- [ ] **Step 7: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: All tests pass (previous 43 + 23 new = 66 total).

- [ ] **Step 8: Commit**

```bash
git add app/Services/PaymentService.php app/Providers/AppServiceProvider.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat: add PaymentService with Stripe integration and AppServiceProvider binding"
```
