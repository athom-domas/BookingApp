# Eloquent Models – Phase 2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create all Eloquent models with relationships, query scopes, and helper methods for the booking management system.

**Architecture:** Models live in `app/Models/`, each paired with a factory in `database/factories/`. Use the PHP 8 attribute syntax (`#[Fillable]`) consistent with the existing `User` model. Tests use Pest 4 with `RefreshDatabase` against the PostgreSQL container via Docker.

**Tech Stack:** Laravel 13, Eloquent ORM, Pest 4, Spatie Permission (HasRoles already on User), PostgreSQL, Docker Compose.

---

## Schema reference (existing migrations)

| Table | Key columns |
|---|---|
| `users` | id, name, email, password |
| `services` | id, name, description, duration_minutes, price, active |
| `availability_rules` | id, user_id, day_of_week (0–6), start_time, end_time, is_available |
| `appointments` | id, user_id (customer), service_id, staff_id (→users), scheduled_date (timestamp), status (pending/confirmed/cancelled/completed), final_price, notes, google_event_id |
| `time_slots` | id, user_id (staff), date, start_time, end_time, is_available, appointment_id (nullable) |
| `appointment_reminders` | id, appointment_id, type (email/sms), scheduled_for, sent_at (nullable), status (pending/sent/failed), error_message (nullable) |
| `payments` | id, appointment_id, user_id, amount, status (pending/completed/refunded/failed), stripe_transaction_id (nullable, unique), stripe_response (json, nullable) |
| `service_staff` | id, service_id, user_id |
| `user_preferences` | id, user_id (unique), receive_email_reminders, receive_sms_reminders, phone_number, timezone, preferred_staff (nullable →users) |

## File map

**Create:**
- `app/Models/Service.php`
- `app/Models/AvailabilityRule.php`
- `app/Models/Appointment.php`
- `app/Models/TimeSlot.php`
- `app/Models/AppointmentReminder.php`
- `app/Models/Payment.php`
- `app/Models/UserPreference.php`
- `database/factories/ServiceFactory.php`
- `database/factories/AvailabilityRuleFactory.php`
- `database/factories/AppointmentFactory.php`
- `database/factories/TimeSlotFactory.php`
- `database/factories/AppointmentReminderFactory.php`
- `database/factories/PaymentFactory.php`
- `database/factories/UserPreferenceFactory.php`
- `tests/Feature/Models/ServiceTest.php`
- `tests/Feature/Models/AppointmentTest.php`
- `tests/Feature/Models/TimeSlotTest.php`
- `tests/Feature/Models/AppointmentReminderTest.php`
- `tests/Feature/Models/PaymentTest.php`
- `tests/Feature/Models/UserTest.php`

**Modify:**
- `tests/Pest.php` – uncomment `->use(RefreshDatabase::class)`
- `app/Models/User.php` – add 7 relationships + 3 helper methods

---

### Task 1: Enable RefreshDatabase and create Service + AvailabilityRule models

**Files:**
- Modify: `tests/Pest.php`
- Create: `app/Models/Service.php`
- Create: `app/Models/AvailabilityRule.php`
- Create: `database/factories/ServiceFactory.php`
- Create: `database/factories/AvailabilityRuleFactory.php`
- Create: `tests/Feature/Models/ServiceTest.php`

- [ ] **Step 1: Enable RefreshDatabase globally for Feature tests**

In `tests/Pest.php`, change:
```php
pest()->extend(TestCase::class)
 // ->use(RefreshDatabase::class)
    ->in('Feature');
```
to:
```php
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

- [ ] **Step 2: Write failing test for Service model**

Create `tests/Feature/Models/ServiceTest.php`:
```php
<?php

use App\Models\Service;
use App\Models\User;

it('has active scope', function () {
    Service::factory()->create(['active' => true]);
    Service::factory()->create(['active' => false]);

    expect(Service::active()->count())->toBe(1);
});

it('belongs to many staff users via service_staff', function () {
    $service = Service::factory()->create();
    $user = User::factory()->create();

    $service->staff()->attach($user->id);

    expect($service->staff)->toHaveCount(1);
    expect($service->staff->first()->id)->toBe($user->id);
});
```

- [ ] **Step 3: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/ServiceTest.php
```

Expected: FAIL — `App\Models\Service` not found.

- [ ] **Step 4: Create ServiceFactory**

Create `database/factories/ServiceFactory.php`:
```php
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ServiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'duration_minutes' => fake()->randomElement([30, 45, 60, 90]),
            'price' => fake()->randomFloat(2, 20, 200),
            'active' => true,
        ];
    }
}
```

- [ ] **Step 5: Create Service model**

Create `app/Models/Service.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'description', 'duration_minutes', 'price', 'active'])]
class Service extends Model
{
    use HasFactory;

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'service_staff');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }
}
```

- [ ] **Step 6: Create AvailabilityRuleFactory**

Create `database/factories/AvailabilityRuleFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AvailabilityRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'is_available' => true,
        ];
    }
}
```

- [ ] **Step 7: Create AvailabilityRule model**

Create `app/Models/AvailabilityRule.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'day_of_week', 'start_time', 'end_time', 'is_available'])]
class AvailabilityRule extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

- [ ] **Step 8: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/ServiceTest.php
```

Expected: PASS — 2 tests passed.

- [ ] **Step 9: Commit**

```bash
git add app/Models/Service.php app/Models/AvailabilityRule.php \
    database/factories/ServiceFactory.php database/factories/AvailabilityRuleFactory.php \
    tests/Feature/Models/ServiceTest.php tests/Pest.php
git commit -m "feat: add Service and AvailabilityRule models"
```

---

### Task 2: Appointment model with scopes and helpers

**Files:**
- Create: `app/Models/Appointment.php`
- Create: `database/factories/AppointmentFactory.php`
- Create: `tests/Feature/Models/AppointmentTest.php`

- [ ] **Step 1: Write failing test for Appointment model**

Create `tests/Feature/Models/AppointmentTest.php`:
```php
<?php

use App\Models\Appointment;
use App\Models\AppointmentReminder;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;

it('belongs to a customer user', function () {
    $customer = User::factory()->create();
    $appointment = Appointment::factory()->create(['user_id' => $customer->id]);

    expect($appointment->user->id)->toBe($customer->id);
});

it('belongs to a staff user', function () {
    $staff = User::factory()->create();
    $appointment = Appointment::factory()->create(['staff_id' => $staff->id]);

    expect($appointment->staff->id)->toBe($staff->id);
});

it('belongs to a service', function () {
    $service = Service::factory()->create();
    $appointment = Appointment::factory()->create(['service_id' => $service->id]);

    expect($appointment->service->id)->toBe($service->id);
});

it('has many reminders', function () {
    $appointment = Appointment::factory()->create();
    AppointmentReminder::factory()->count(2)->create(['appointment_id' => $appointment->id]);

    expect($appointment->reminders)->toHaveCount(2);
});

it('has one payment', function () {
    $appointment = Appointment::factory()->create();
    Payment::factory()->create(['appointment_id' => $appointment->id]);

    expect($appointment->payment)->toBeInstanceOf(Payment::class);
});

it('scope upcoming returns future appointments', function () {
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5)]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5)]);

    expect(Appointment::upcoming()->count())->toBe(1);
});

it('scope pastAppointments returns past appointments', function () {
    Appointment::factory()->create(['scheduled_date' => now()->addDays(5)]);
    Appointment::factory()->create(['scheduled_date' => now()->subDays(5)]);

    expect(Appointment::pastAppointments()->count())->toBe(1);
});

it('scope confirmed returns only confirmed appointments', function () {
    Appointment::factory()->create(['status' => 'confirmed']);
    Appointment::factory()->create(['status' => 'pending']);

    expect(Appointment::confirmed()->count())->toBe(1);
});

it('isPast returns true for past appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->subDay()]);

    expect($appointment->isPast())->toBeTrue();
});

it('isUpcoming returns true for future appointments', function () {
    $appointment = Appointment::factory()->create(['scheduled_date' => now()->addDay()]);

    expect($appointment->isUpcoming())->toBeTrue();
});

it('canBeCancelled returns true for pending future appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDay(),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeTrue();
});

it('canBeCancelled returns false for completed appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->addDay(),
        'status' => 'completed',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});

it('canBeCancelled returns false for past appointments', function () {
    $appointment = Appointment::factory()->create([
        'scheduled_date' => now()->subDay(),
        'status' => 'pending',
    ]);

    expect($appointment->canBeCancelled())->toBeFalse();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/AppointmentTest.php
```

Expected: FAIL — `App\Models\Appointment` not found.

- [ ] **Step 3: Create AppointmentFactory**

Create `database/factories/AppointmentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'service_id' => Service::factory(),
            'staff_id' => User::factory(),
            'scheduled_date' => now()->addDays(fake()->numberBetween(1, 30)),
            'status' => 'pending',
            'final_price' => null,
            'notes' => null,
            'google_event_id' => null,
        ];
    }
}
```

- [ ] **Step 4: Create Appointment model**

Create `app/Models/Appointment.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['user_id', 'service_id', 'staff_id', 'scheduled_date', 'status', 'final_price', 'notes', 'google_event_id'])]
class Appointment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'datetime',
            'final_price' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(AppointmentReminder::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function scopeUpcoming(Builder $query): void
    {
        $query->where('scheduled_date', '>', now());
    }

    public function scopePastAppointments(Builder $query): void
    {
        $query->where('scheduled_date', '<', now());
    }

    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', 'confirmed');
    }

    public function isPast(): bool
    {
        return $this->scheduled_date->isPast();
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_date->isFuture();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['pending', 'confirmed']) && $this->isUpcoming();
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/AppointmentTest.php
```

Expected: PASS — 13 tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Appointment.php database/factories/AppointmentFactory.php \
    tests/Feature/Models/AppointmentTest.php
git commit -m "feat: add Appointment model with scopes and helpers"
```

---

### Task 3: TimeSlot model

**Files:**
- Create: `app/Models/TimeSlot.php`
- Create: `database/factories/TimeSlotFactory.php`
- Create: `tests/Feature/Models/TimeSlotTest.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Models/TimeSlotTest.php`:
```php
<?php

use App\Models\Appointment;
use App\Models\TimeSlot;
use App\Models\User;

it('belongs to a staff user', function () {
    $user = User::factory()->create();
    $slot = TimeSlot::factory()->create(['user_id' => $user->id]);

    expect($slot->user->id)->toBe($user->id);
});

it('belongs to an appointment when booked', function () {
    $appointment = Appointment::factory()->create();
    $slot = TimeSlot::factory()->create(['appointment_id' => $appointment->id, 'is_available' => false]);

    expect($slot->appointment->id)->toBe($appointment->id);
});

it('appointment is null when free', function () {
    $slot = TimeSlot::factory()->create(['appointment_id' => null]);

    expect($slot->appointment)->toBeNull();
});

it('scope available returns slots with no appointment and is_available true', function () {
    TimeSlot::factory()->create(['is_available' => true, 'appointment_id' => null]);
    TimeSlot::factory()->create(['is_available' => false, 'appointment_id' => null]);
    $booked = Appointment::factory()->create();
    TimeSlot::factory()->create(['is_available' => true, 'appointment_id' => $booked->id]);

    expect(TimeSlot::available()->count())->toBe(1);
});

it('scope forDate returns slots for the given date', function () {
    TimeSlot::factory()->create(['date' => '2026-06-01']);
    TimeSlot::factory()->create(['date' => '2026-06-02']);

    expect(TimeSlot::forDate('2026-06-01')->count())->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/TimeSlotTest.php
```

Expected: FAIL — `App\Models\TimeSlot` not found.

- [ ] **Step 3: Create TimeSlotFactory**

Create `database/factories/TimeSlotFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TimeSlotFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => now()->addDays(fake()->numberBetween(1, 30))->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'is_available' => true,
            'appointment_id' => null,
        ];
    }
}
```

- [ ] **Step 4: Create TimeSlot model**

Create `app/Models/TimeSlot.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'date', 'start_time', 'end_time', 'is_available', 'appointment_id'])]
class TimeSlot extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopeAvailable(Builder $query): void
    {
        $query->where('is_available', true)->whereNull('appointment_id');
    }

    public function scopeForDate(Builder $query, string $date): void
    {
        $query->where('date', $date);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/TimeSlotTest.php
```

Expected: PASS — 5 tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/TimeSlot.php database/factories/TimeSlotFactory.php \
    tests/Feature/Models/TimeSlotTest.php
git commit -m "feat: add TimeSlot model with available and forDate scopes"
```

---

### Task 4: AppointmentReminder and Payment models

**Files:**
- Create: `app/Models/AppointmentReminder.php`
- Create: `app/Models/Payment.php`
- Create: `database/factories/AppointmentReminderFactory.php`
- Create: `database/factories/PaymentFactory.php`
- Create: `tests/Feature/Models/AppointmentReminderTest.php`
- Create: `tests/Feature/Models/PaymentTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Models/AppointmentReminderTest.php`:
```php
<?php

use App\Models\Appointment;
use App\Models\AppointmentReminder;

it('belongs to an appointment', function () {
    $appointment = Appointment::factory()->create();
    $reminder = AppointmentReminder::factory()->create(['appointment_id' => $appointment->id]);

    expect($reminder->appointment->id)->toBe($appointment->id);
});

it('scope pending returns only pending reminders', function () {
    AppointmentReminder::factory()->create(['status' => 'pending']);
    AppointmentReminder::factory()->create(['status' => 'sent']);
    AppointmentReminder::factory()->create(['status' => 'failed']);

    expect(AppointmentReminder::pending()->count())->toBe(1);
});
```

Create `tests/Feature/Models/PaymentTest.php`:
```php
<?php

use App\Models\Appointment;
use App\Models\Payment;
use App\Models\User;

it('belongs to an appointment', function () {
    $appointment = Appointment::factory()->create();
    $payment = Payment::factory()->create(['appointment_id' => $appointment->id]);

    expect($payment->appointment->id)->toBe($appointment->id);
});

it('belongs to a user', function () {
    $user = User::factory()->create();
    $payment = Payment::factory()->create(['user_id' => $user->id]);

    expect($payment->user->id)->toBe($user->id);
});

it('scope paid returns only completed payments', function () {
    Payment::factory()->create(['status' => 'completed']);
    Payment::factory()->create(['status' => 'pending']);
    Payment::factory()->create(['status' => 'failed']);

    expect(Payment::paid()->count())->toBe(1);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/AppointmentReminderTest.php tests/Feature/Models/PaymentTest.php
```

Expected: FAIL — classes not found.

- [ ] **Step 3: Create AppointmentReminderFactory**

Create `database/factories/AppointmentReminderFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Appointment;
use Illuminate\Database\Eloquent\Factories\Factory;

class AppointmentReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'type' => fake()->randomElement(['email', 'sms']),
            'scheduled_for' => now()->addHours(fake()->numberBetween(1, 48)),
            'sent_at' => null,
            'status' => 'pending',
            'error_message' => null,
        ];
    }
}
```

- [ ] **Step 4: Create AppointmentReminder model**

Create `app/Models/AppointmentReminder.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'type', 'scheduled_for', 'sent_at', 'status', 'error_message'])]
class AppointmentReminder extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }
}
```

- [ ] **Step 5: Create PaymentFactory**

Create `database/factories/PaymentFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'appointment_id' => Appointment::factory(),
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 10, 500),
            'status' => 'pending',
            'stripe_transaction_id' => null,
            'stripe_response' => null,
        ];
    }
}
```

- [ ] **Step 6: Create Payment model**

Create `app/Models/Payment.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'user_id', 'amount', 'status', 'stripe_transaction_id', 'stripe_response'])]
class Payment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'stripe_response' => 'array',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePaid(Builder $query): void
    {
        $query->where('status', 'completed');
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/AppointmentReminderTest.php tests/Feature/Models/PaymentTest.php
```

Expected: PASS — 5 tests passed.

- [ ] **Step 8: Commit**

```bash
git add app/Models/AppointmentReminder.php app/Models/Payment.php \
    database/factories/AppointmentReminderFactory.php database/factories/PaymentFactory.php \
    tests/Feature/Models/AppointmentReminderTest.php tests/Feature/Models/PaymentTest.php
git commit -m "feat: add AppointmentReminder and Payment models"
```

---

### Task 5: UserPreference model and User model relationships

**Files:**
- Create: `app/Models/UserPreference.php`
- Create: `database/factories/UserPreferenceFactory.php`
- Create: `tests/Feature/Models/UserTest.php`
- Modify: `app/Models/User.php`

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Models/UserTest.php`:
```php
<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\UserPreference;

it('has many appointments as customer', function () {
    $user = User::factory()->create();
    Appointment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->appointmentsAsCustomer)->toHaveCount(2);
});

it('has many appointments as staff', function () {
    $staff = User::factory()->create();
    Appointment::factory()->count(3)->create(['staff_id' => $staff->id]);

    expect($staff->appointmentsAsStaff)->toHaveCount(3);
});

it('belongs to many services via service_staff', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();
    $service->staff()->attach($user->id);

    expect($user->services)->toHaveCount(1);
    expect($user->services->first()->id)->toBe($service->id);
});

it('has many availability rules', function () {
    $user = User::factory()->create();
    AvailabilityRule::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->availabilityRules)->toHaveCount(3);
});

it('has many time slots', function () {
    $user = User::factory()->create();
    TimeSlot::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->timeSlots)->toHaveCount(2);
});

it('has one preference', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create(['user_id' => $user->id]);

    expect($user->preferences)->toBeInstanceOf(UserPreference::class);
});

it('has many payments', function () {
    $user = User::factory()->create();
    Payment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->payments)->toHaveCount(2);
});

it('isAdmin returns true when user has admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->isAdmin())->toBeTrue();
    expect($user->isStaff())->toBeFalse();
    expect($user->isCustomer())->toBeFalse();
});

it('isStaff returns true when user has staff role', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect($user->isStaff())->toBeTrue();
});

it('isCustomer returns true when user has customer role', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    expect($user->isCustomer())->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/UserTest.php
```

Expected: FAIL — relationships and methods not defined on User.

- [ ] **Step 3: Create UserPreferenceFactory**

Create `database/factories/UserPreferenceFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserPreferenceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'receive_email_reminders' => true,
            'receive_sms_reminders' => false,
            'phone_number' => null,
            'timezone' => 'UTC',
            'preferred_staff' => null,
        ];
    }
}
```

- [ ] **Step 4: Create UserPreference model**

Create `app/Models/UserPreference.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'receive_email_reminders', 'receive_sms_reminders', 'phone_number', 'timezone', 'preferred_staff'])]
class UserPreference extends Model
{
    use HasFactory;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function preferredStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'preferred_staff');
    }
}
```

- [ ] **Step 5: Update User model with relationships and helpers**

Replace the content of `app/Models/User.php` with:
```php
<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function appointmentsAsCustomer(): HasMany
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    public function appointmentsAsStaff(): HasMany
    {
        return $this->hasMany(Appointment::class, 'staff_id');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'service_staff');
    }

    public function availabilityRules(): HasMany
    {
        return $this->hasMany(AvailabilityRule::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function preferences(): HasOne
    {
        return $this->hasOne(UserPreference::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isStaff(): bool
    {
        return $this->hasRole('staff');
    }

    public function isCustomer(): bool
    {
        return $this->hasRole('customer');
    }
}
```

- [ ] **Step 6: Seed the roles needed by the test**

The `assignRole('admin')` test requires the role to exist in the database. Add a `beforeEach` to create roles, or use `createRole`. In `tests/Feature/Models/UserTest.php`, add at the top after the `use` statements:

```php
beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});
```

The full `tests/Feature/Models/UserTest.php` is:
```php
<?php

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\UserPreference;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
});

it('has many appointments as customer', function () {
    $user = User::factory()->create();
    Appointment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->appointmentsAsCustomer)->toHaveCount(2);
});

it('has many appointments as staff', function () {
    $staff = User::factory()->create();
    Appointment::factory()->count(3)->create(['staff_id' => $staff->id]);

    expect($staff->appointmentsAsStaff)->toHaveCount(3);
});

it('belongs to many services via service_staff', function () {
    $user = User::factory()->create();
    $service = Service::factory()->create();
    $service->staff()->attach($user->id);

    expect($user->services)->toHaveCount(1);
    expect($user->services->first()->id)->toBe($service->id);
});

it('has many availability rules', function () {
    $user = User::factory()->create();
    AvailabilityRule::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->availabilityRules)->toHaveCount(3);
});

it('has many time slots', function () {
    $user = User::factory()->create();
    TimeSlot::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->timeSlots)->toHaveCount(2);
});

it('has one preference', function () {
    $user = User::factory()->create();
    UserPreference::factory()->create(['user_id' => $user->id]);

    expect($user->preferences)->toBeInstanceOf(UserPreference::class);
});

it('has many payments', function () {
    $user = User::factory()->create();
    Payment::factory()->count(2)->create(['user_id' => $user->id]);

    expect($user->payments)->toHaveCount(2);
});

it('isAdmin returns true when user has admin role', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($user->isAdmin())->toBeTrue();
    expect($user->isStaff())->toBeFalse();
    expect($user->isCustomer())->toBeFalse();
});

it('isStaff returns true when user has staff role', function () {
    $user = User::factory()->create();
    $user->assignRole('staff');

    expect($user->isStaff())->toBeTrue();
});

it('isCustomer returns true when user has customer role', function () {
    $user = User::factory()->create();
    $user->assignRole('customer');

    expect($user->isCustomer())->toBeTrue();
});
```

- [ ] **Step 7: Run test to verify it passes**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/UserTest.php
```

Expected: PASS — 10 tests passed.

- [ ] **Step 8: Run full test suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: All tests passed (previous 2 + new model tests).

- [ ] **Step 9: Commit**

```bash
git add app/Models/User.php app/Models/UserPreference.php \
    database/factories/UserPreferenceFactory.php \
    tests/Feature/Models/UserTest.php
git commit -m "feat: add UserPreference model and User relationships/helpers"
```
