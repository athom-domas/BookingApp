# Follow-up Reminders — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Inviare un'email automatica ai clienti che non prenotano entro N giorni dal loro ultimo appuntamento, con opt-out cliente via toggle nel portale e link firmato di disiscrizione nell'email.

**Architecture:** Nuova tabella `follow_up_reminders` separata da `appointment_reminders` (semantica incompatibile). Il trigger è `AppointmentObserver::updated()` su status `completed`. Il job `SendFollowUpReminder` esegue un claim atomico per concorrenza e ri-verifica l'eligibilità prima di inviare. Scheduler a 5 minuti con chunking; recovery stale ogni ora.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Pest. Segue le convenzioni esistenti: `BelongsToBusiness` trait, `#[Fillable]` attribute, `protected function casts(): array`, metodi statici su `SystemSetting`.

**Spec:** `docs/superpowers/specs/2026-06-18-follow-up-marketing.md`

---

## File Structure

**Nuovi file:**
- `database/migrations/2026_06_18_000004_create_follow_up_reminders_table.php`
- `database/migrations/2026_06_18_000005_add_follow_up_reminders_enabled_to_user_preferences.php`
- `database/migrations/2026_06_18_000006_add_follow_up_reminders_to_system_settings.php`
- `app/Models/FollowUpReminder.php`
- `database/factories/FollowUpReminderFactory.php`
- `app/Jobs/SendFollowUpReminder.php`
- `app/Mail/FollowUpReminderMail.php`
- `resources/views/emails/follow-up-reminder.blade.php`
- `app/Http/Controllers/FollowUpReminderUnsubscribeController.php`
- `resources/views/portal/follow-up-reminders/unsubscribed.blade.php`
- `tests/Feature/FollowUpReminderTest.php`

**File modificati:**
- `app/Models/SystemSetting.php` — fillable, casts, defaults, 2 metodi statici
- `app/Models/UserPreference.php` — fillable, casts
- `app/Observers/AppointmentObserver.php` — `scheduleFollowUpReminder()` privato
- `app/Filament/Pages/SystemSettings.php` — sezione + mount + save
- `resources/views/portal/settings/index.blade.php` — sezione "Comunicazioni"
- `app/Http/Controllers/Portal/SettingsController.php` — gestione `follow_up_reminders_enabled`
- `routes/web.php` — route unsubscribe
- `routes/console.php` — 2 schedule

---

## Task 1: Migrazioni (3 file)

**Files:**
- Create: `database/migrations/2026_06_18_000004_create_follow_up_reminders_table.php`
- Create: `database/migrations/2026_06_18_000005_add_follow_up_reminders_enabled_to_user_preferences.php`
- Create: `database/migrations/2026_06_18_000006_add_follow_up_reminders_to_system_settings.php`

- [ ] **Step 1: Crea la migrazione per `follow_up_reminders`**

```php
<?php
// database/migrations/2026_06_18_000004_create_follow_up_reminders_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_up_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('rebooking');
            $table->string('channel')->default('email');
            $table->unsignedSmallInteger('delay_days');
            $table->dateTime('scheduled_for');
            $table->dateTime('sent_at')->nullable();
            $table->enum('status', ['pending', 'processing', 'sent', 'failed', 'skipped'])->default('pending');
            $table->dateTime('processing_at')->nullable();
            $table->string('skipped_reason')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_for']);
            $table->index(['business_id', 'status', 'scheduled_for']);
            $table->index(['business_id', 'user_id', 'type', 'status']);
            $table->index(['business_id', 'user_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_up_reminders');
    }
};
```

- [ ] **Step 2: Crea la migrazione per `user_preferences`**

```php
<?php
// database/migrations/2026_06_18_000005_add_follow_up_reminders_enabled_to_user_preferences.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->boolean('follow_up_reminders_enabled')->default(true)->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('user_preferences', function (Blueprint $table) {
            $table->dropColumn('follow_up_reminders_enabled');
        });
    }
};
```

- [ ] **Step 3: Crea la migrazione per `system_settings`**

```php
<?php
// database/migrations/2026_06_18_000006_add_follow_up_reminders_to_system_settings.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->boolean('follow_up_reminders_enabled')->default(false)->after('review_request_delay_hours');
            $table->unsignedSmallInteger('follow_up_reminder_days')->default(30)->after('follow_up_reminders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn(['follow_up_reminders_enabled', 'follow_up_reminder_days']);
        });
    }
};
```

- [ ] **Step 4: Esegui le migrazioni**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `Migrating: 2026_06_18_000004_create_follow_up_reminders_table` (e le altre due) → `Migrated`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_06_18_000004_create_follow_up_reminders_table.php
git add database/migrations/2026_06_18_000005_add_follow_up_reminders_enabled_to_user_preferences.php
git add database/migrations/2026_06_18_000006_add_follow_up_reminders_to_system_settings.php
git commit -m "feat: add follow_up_reminders table and related columns"
```

---

## Task 2: Modello `FollowUpReminder` e Factory

**Files:**
- Create: `app/Models/FollowUpReminder.php`
- Create: `database/factories/FollowUpReminderFactory.php`

- [ ] **Step 1: Crea il modello**

```php
<?php
// app/Models/FollowUpReminder.php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<\Database\Factories\FollowUpReminderFactory> */
class FollowUpReminder extends Model
{
    use BelongsToBusiness, HasFactory;

    #[Fillable(['business_id', 'user_id', 'appointment_id', 'type', 'channel', 'delay_days',
                'scheduled_for', 'sent_at', 'status', 'processing_at', 'skipped_reason', 'error_message'])]

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at'       => 'datetime',
            'processing_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_for', '<=', now());
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->where('status', 'processing')
                     ->where('processing_at', '<=', now()->subMinutes(60));
    }
}
```

- [ ] **Step 2: Crea la factory**

```php
<?php
// database/factories/FollowUpReminderFactory.php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FollowUpReminder>
 */
class FollowUpReminderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'    => app()->bound('current_business_id') ? app('current_business_id') : 1,
            'user_id'        => User::factory(),
            'appointment_id' => Appointment::factory(),
            'type'           => 'rebooking',
            'channel'        => 'email',
            'delay_days'     => 30,
            'scheduled_for'  => now()->addDays(30),
            'sent_at'        => null,
            'status'         => 'pending',
            'processing_at'  => null,
            'skipped_reason' => null,
            'error_message'  => null,
        ];
    }
}
```

- [ ] **Step 3: Commit**

```bash
git add app/Models/FollowUpReminder.php database/factories/FollowUpReminderFactory.php
git commit -m "feat: add FollowUpReminder model and factory"
```

---

## Task 3: Aggiorna `SystemSetting` e `UserPreference`

**Files:**
- Modify: `app/Models/SystemSetting.php`
- Modify: `app/Models/UserPreference.php`

- [ ] **Step 1: Aggiungi i nuovi campi a `SystemSetting`**

Nel file `app/Models/SystemSetting.php`:

1. Aggiungi `'follow_up_reminders_enabled'` e `'follow_up_reminder_days'` all'attributo `#[Fillable([...])]` (alla fine dell'array esistente).

2. In `casts()`, aggiungi prima del `return`:
```php
'follow_up_reminders_enabled' => 'boolean',
'follow_up_reminder_days'     => 'integer',
```

3. In `current()`, nel blocco `new self([...])` (il fallback senza business_id), aggiungi alla fine dell'array:
```php
'follow_up_reminders_enabled' => false,
'follow_up_reminder_days'     => 30,
```

4. In `current()`, nel blocco `firstOrCreate([...], [...])` (il secondo array, i default), aggiungi:
```php
'follow_up_reminders_enabled' => false,
'follow_up_reminder_days'     => 30,
```

5. Aggiungi i due metodi statici alla fine della classe (prima della `}`):

```php
public static function isFollowUpRemindersEnabled(): bool
{
    return self::current()->follow_up_reminders_enabled ?? false;
}

public static function getFollowUpReminderDays(): int
{
    return self::current()->follow_up_reminder_days ?? 30;
}
```

- [ ] **Step 2: Aggiungi il campo a `UserPreference`**

In `app/Models/UserPreference.php`:

1. Nell'attributo `#[Fillable([...])]`, aggiungi `'follow_up_reminders_enabled'` alla lista.

2. Aggiungi il metodo `casts()` (il modello non ce l'ha ancora) subito dopo `use BelongsToBusiness, HasFactory;`:

```php
protected function casts(): array
{
    return [
        'follow_up_reminders_enabled' => 'boolean',
    ];
}
```

- [ ] **Step 3: Scrivi i test per i nuovi metodi di `SystemSetting`**

Crea `tests/Feature/FollowUpReminderTest.php`:

```php
<?php

use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use App\Models\UserPreference;
use App\Models\User;
use App\Models\Appointment;
use App\Mail\FollowUpReminderMail;
use App\Jobs\SendFollowUpReminder;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

// SystemSetting helpers
it('isFollowUpRemindersEnabled returns false by default', function () {
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeFalse();
});

it('getFollowUpReminderDays returns 30 by default', function () {
    expect(SystemSetting::getFollowUpReminderDays())->toBe(30);
});

it('isFollowUpRemindersEnabled returns true when enabled', function () {
    SystemSetting::current()->update(['follow_up_reminders_enabled' => true]);
    expect(SystemSetting::isFollowUpRemindersEnabled())->toBeTrue();
});

// UserPreference
it('follow_up_reminders_enabled defaults to true on new preferences', function () {
    $pref = UserPreference::factory()->create();
    expect($pref->follow_up_reminders_enabled)->toBeTrue();
});
```

- [ ] **Step 4: Esegui i test per verificare che falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php
```

Expected: test falliscono con errori relativi a colonne mancanti o metodi non trovati (dipende dall'ordine di esecuzione delle migrazioni in test).

- [ ] **Step 5: Esegui i test con le nuove migrazioni**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php
```

Expected: 4 test passano.

- [ ] **Step 6: Commit**

```bash
git add app/Models/SystemSetting.php app/Models/UserPreference.php tests/Feature/FollowUpReminderTest.php
git commit -m "feat: add follow-up reminder settings to SystemSetting and UserPreference"
```

---

## Task 4: Trigger in `AppointmentObserver`

**Files:**
- Modify: `app/Observers/AppointmentObserver.php`

- [ ] **Step 1: Aggiungi i test del trigger a `FollowUpReminderTest.php`**

Aggiungi questi test al file `tests/Feature/FollowUpReminderTest.php`:

```php
// ---- Observer trigger tests ----

function makeEnabledSettings(): void
{
    SystemSetting::current()->update([
        'follow_up_reminders_enabled' => true,
        'follow_up_reminder_days'     => 30,
    ]);
}

function makeCustomerWithPrefs(bool $followUpEnabled = true): User
{
    $user = User::factory()->create();
    UserPreference::factory()->create([
        'user_id'                     => $user->id,
        'follow_up_reminders_enabled' => $followUpEnabled,
    ]);
    return $user;
}

it('creates a follow-up reminder when appointment is completed and feature is enabled', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
    $reminder = FollowUpReminder::where('user_id', $customer->id)->first();
    expect($reminder->type)->toBe('rebooking');
    expect($reminder->status)->toBe('pending');
    expect($reminder->delay_days)->toBe(30);
    expect($reminder->appointment_id)->toBe($appt->id);
});

it('does not create a reminder if feature is disabled', function () {
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create(['user_id' => $customer->id, 'status' => 'confirmed']);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a reminder if user has follow_up_reminders_enabled = false', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs(followUpEnabled: false);
    $appt = Appointment::factory()->create(['user_id' => $customer->id, 'status' => 'confirmed']);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a reminder if user has a future appointment', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(5),
    ]);
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(0);
});

it('does not create a duplicate reminder for the same appointment', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt->update(['status' => 'completed']);
    $appt->touch(); // trigger updated again
    $appt->update(['status' => 'completed']); // fire observer again

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
});

it('does not create a duplicate pending reminder for same user and business', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt1 = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(10),
    ]);
    $appt2 = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->subDays(1),
    ]);

    $appt1->update(['status' => 'completed']);
    $appt2->update(['status' => 'completed']);

    expect(FollowUpReminder::where('user_id', $customer->id)->count())->toBe(1);
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php --filter "creates a follow-up reminder"
```

Expected: FAIL (metodo `scheduleFollowUpReminder` non esiste).

- [ ] **Step 3: Implementa il trigger nell'observer**

In `app/Observers/AppointmentObserver.php`:

1. Aggiungi l'import `use App\Models\FollowUpReminder;` dopo le import esistenti.

2. Nel metodo `updated()`, cambia il branch `elseif ($appointment->status === 'completed')` da:
```php
} elseif ($appointment->status === 'completed') {
    $this->scheduleReviewRequest($appointment);
}
```
a:
```php
} elseif ($appointment->status === 'completed') {
    $this->scheduleReviewRequest($appointment);
    $this->scheduleFollowUpReminder($appointment);
}
```

3. Aggiungi il metodo privato alla fine della classe (prima della `}`):

```php
private function scheduleFollowUpReminder(Appointment $appointment): void
{
    if (! app()->bound('current_business_id')) {
        app()->instance('current_business_id', $appointment->business_id);
    }

    if (! SystemSetting::isFollowUpRemindersEnabled()) {
        return;
    }

    if (! $appointment->user_id) {
        return;
    }

    $prefs = $appointment->user->preferences;

    if (! $prefs || ! $prefs->follow_up_reminders_enabled) {
        return;
    }

    $hasFutureAppointment = Appointment::where('user_id', $appointment->user_id)
        ->where('business_id', $appointment->business_id)
        ->whereIn('status', ['pending', 'confirmed'])
        ->where('scheduled_date', '>', now())
        ->exists();

    if ($hasFutureAppointment) {
        return;
    }

    $existsForAppointment = FollowUpReminder::where('appointment_id', $appointment->id)
        ->where('type', 'rebooking')
        ->whereIn('status', ['pending', 'processing', 'sent'])
        ->exists();

    if ($existsForAppointment) {
        return;
    }

    $existsForUser = FollowUpReminder::where('business_id', $appointment->business_id)
        ->where('user_id', $appointment->user_id)
        ->where('type', 'rebooking')
        ->whereIn('status', ['pending', 'processing'])
        ->exists();

    if ($existsForUser) {
        return;
    }

    $days = SystemSetting::getFollowUpReminderDays();

    FollowUpReminder::create([
        'business_id'    => $appointment->business_id,
        'user_id'        => $appointment->user_id,
        'appointment_id' => $appointment->id,
        'type'           => 'rebooking',
        'delay_days'     => $days,
        'scheduled_for'  => now()->addDays($days),
        'status'         => 'pending',
    ]);
}
```

- [ ] **Step 4: Esegui i test del trigger**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php
```

Expected: tutti i test passano.

- [ ] **Step 5: Commit**

```bash
git add app/Observers/AppointmentObserver.php tests/Feature/FollowUpReminderTest.php
git commit -m "feat: trigger follow-up reminder on appointment completed"
```

---

## Task 5: Job `SendFollowUpReminder`

**Files:**
- Create: `app/Jobs/SendFollowUpReminder.php`

- [ ] **Step 1: Aggiungi i test del job a `FollowUpReminderTest.php`**

**Nota:** i test per "sent" e "failed" vengono aggiunti al Task 6, dopo che `FollowUpReminderMail` è stata creata. Qui aggiungi solo i test di skip e il claim atomico.

```php
// ---- Job tests ----

it('job skips if admin disables feature after reminder creation', function () {
    Mail::fake();
    $customer = makeCustomerWithPrefs();
    $reminder = FollowUpReminder::factory()->create([
        'user_id'       => $customer->id,
        'delay_days'    => 30,
        'scheduled_for' => now()->subMinute(),
        'status'        => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('feature_disabled');
    Mail::assertNotSent(FollowUpReminderMail::class);
});

it('job skips if user disables follow-up after reminder creation', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs(followUpEnabled: false);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'       => $customer->id,
        'delay_days'    => 30,
        'scheduled_for' => now()->subMinute(),
        'status'        => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('user_disabled');
});

it('job skips if user books a future appointment before send time', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'confirmed',
        'scheduled_date' => now()->addDays(5),
    ]);
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('user_has_future_appointment');
});

it('job skips if user completed a newer appointment more recently than delay_days', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $originalAppt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(40),
    ]);
    // Newer appointment completed only 5 days ago (within 30-day window)
    Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(5),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $originalAppt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('skipped');
    expect($reminder->fresh()->skipped_reason)->toBe('recent_appointment_completed');
});

it('second job invocation on same reminder does not send (atomic claim)', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();
    (new SendFollowUpReminder($reminder->id))->handle(); // second invocation

    Mail::assertSentCount(1);
    expect($reminder->fresh()->status)->toBe('sent');
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php --filter "job marks reminder as sent"
```

Expected: FAIL (class `SendFollowUpReminder` not found).

- [ ] **Step 3: Implementa il job**

```php
<?php
// app/Jobs/SendFollowUpReminder.php

namespace App\Jobs;

use App\Mail\FollowUpReminderMail;
use App\Models\Appointment;
use App\Models\FollowUpReminder;
use App\Models\SystemSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendFollowUpReminder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $reminderId) {}

    public function handle(): void
    {
        $claimed = FollowUpReminder::whereKey($this->reminderId)
            ->where('status', 'pending')
            ->where('scheduled_for', '<=', now())
            ->update(['status' => 'processing', 'processing_at' => now()]);

        if (! $claimed) {
            return;
        }

        $reminder = FollowUpReminder::findOrFail($this->reminderId);

        app()->instance('current_business_id', $reminder->business_id);

        if (! SystemSetting::isFollowUpRemindersEnabled()) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'feature_disabled']);
            return;
        }

        $prefs = $reminder->user->preferences;

        if (! $prefs || ! $prefs->follow_up_reminders_enabled) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'user_disabled']);
            return;
        }

        $hasFutureAppointment = Appointment::where('user_id', $reminder->user_id)
            ->where('business_id', $reminder->business_id)
            ->whereIn('status', ['pending', 'confirmed'])
            ->where('scheduled_date', '>', now())
            ->exists();

        if ($hasFutureAppointment) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'user_has_future_appointment']);
            return;
        }

        $latestCompleted = Appointment::where('user_id', $reminder->user_id)
            ->where('business_id', $reminder->business_id)
            ->where('status', 'completed')
            ->orderByDesc('scheduled_date')
            ->first();

        if (
            $latestCompleted &&
            $latestCompleted->id !== $reminder->appointment_id &&
            $latestCompleted->scheduled_date->gt(now()->subDays($reminder->delay_days))
        ) {
            $reminder->update(['status' => 'skipped', 'skipped_reason' => 'recent_appointment_completed']);
            return;
        }

        try {
            Mail::to($reminder->user->email)->send(new FollowUpReminderMail($reminder));
            $reminder->update(['status' => 'sent', 'sent_at' => now(), 'channel' => 'email']);
        } catch (\Throwable $e) {
            $reminder->update([
                'status'        => 'failed',
                'error_message' => Str::limit($e->getMessage(), 1000),
            ]);
        }
    }
}
```

- [ ] **Step 4: Esegui tutti i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php
```

Expected: tutti i test passano (tranne quelli sulla mail che richiedono la mail class nel prossimo task — se fallisce solo `FollowUpReminderMail not found` è accettabile, quei test vanno al Task 6).

- [ ] **Step 5: Commit**

```bash
git add app/Jobs/SendFollowUpReminder.php tests/Feature/FollowUpReminderTest.php
git commit -m "feat: add SendFollowUpReminder job with atomic claim and eligibility checks"
```

---

## Task 6: Mail, view email, unsubscribe controller e route

**Files:**
- Create: `app/Mail/FollowUpReminderMail.php`
- Create: `resources/views/emails/follow-up-reminder.blade.php`
- Create: `app/Http/Controllers/FollowUpReminderUnsubscribeController.php`
- Create: `resources/views/portal/follow-up-reminders/unsubscribed.blade.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Aggiungi i test per mail, sent/failed e unsubscribe**

In `tests/Feature/FollowUpReminderTest.php`, aggiungi alla fine:

```php
// ---- Mail sent/failed tests ----

it('job marks reminder as sent and sends email on success', function () {
    Mail::fake();
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('sent');
    expect($reminder->fresh()->sent_at)->not->toBeNull();
    Mail::assertSent(FollowUpReminderMail::class);
});

it('job marks reminder as failed on mail exception', function () {
    makeEnabledSettings();
    $customer = makeCustomerWithPrefs();
    $appt = Appointment::factory()->create([
        'user_id'        => $customer->id,
        'status'         => 'completed',
        'scheduled_date' => now()->subDays(35),
    ]);
    $reminder = FollowUpReminder::factory()->create([
        'user_id'        => $customer->id,
        'appointment_id' => $appt->id,
        'delay_days'     => 30,
        'scheduled_for'  => now()->subMinute(),
        'status'         => 'pending',
    ]);

    $this->mock(\Illuminate\Mail\Mailer::class, function ($mock) {
        $mock->shouldReceive('to')->andReturnSelf();
        $mock->shouldReceive('send')->andThrow(new \RuntimeException('SMTP error'));
    });

    (new SendFollowUpReminder($reminder->id))->handle();

    expect($reminder->fresh()->status)->toBe('failed');
    expect($reminder->fresh()->error_message)->toContain('SMTP error');
});

// ---- Unsubscribe tests ----

it('signed unsubscribe URL sets follow_up_reminders_enabled to false', function () {
    $customer = makeCustomerWithPrefs(followUpEnabled: true);
    $url = \Illuminate\Support\Facades\URL::signedRoute(
        'follow-up-reminders.unsubscribe',
        ['user' => $customer->id]
    );

    $response = $this->get($url);

    $response->assertOk();
    expect($customer->preferences->fresh()->follow_up_reminders_enabled)->toBeFalse();
});

it('unsigned unsubscribe URL returns 403', function () {
    $customer = makeCustomerWithPrefs();
    $url = route('follow-up-reminders.unsubscribe', ['user' => $customer->id]);

    $response = $this->get($url);

    $response->assertForbidden();
});
```

- [ ] **Step 2: Verifica che i test falliscano**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php --filter "unsubscribe"
```

Expected: FAIL (route `follow-up-reminders.unsubscribe` not found).

- [ ] **Step 3: Crea la mail class**

```php
<?php
// app/Mail/FollowUpReminderMail.php

namespace App\Mail;

use App\Models\FollowUpReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class FollowUpReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly FollowUpReminder $reminder) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            to:      $this->reminder->user->email,
            subject: 'Vuoi prenotare un nuovo appuntamento?',
        );
    }

    public function content(): Content
    {
        $unsubscribeUrl = URL::signedRoute(
            'follow-up-reminders.unsubscribe',
            ['user' => $this->reminder->user_id],
            now()->addYear()
        );

        return new Content(
            view: 'emails.follow-up-reminder',
            with: [
                'reminder'       => $this->reminder,
                'bookingUrl'     => route('booking.index'),
                'unsubscribeUrl' => $unsubscribeUrl,
                'noGreeting'     => true,
            ],
        );
    }
}
```

- [ ] **Step 4: Crea la view email**

```blade
{{-- resources/views/emails/follow-up-reminder.blade.php --}}
@extends('emails.layouts.base')

@section('title')Vuoi prenotare un nuovo appuntamento?@endsection

@section('body')
    <p>Ciao {{ explode(' ', trim($reminder->user->name))[0] }}, è passato un po' dal tuo ultimo appuntamento.</p>
    <p>Se vuoi programmare una nuova visita, puoi prenotare direttamente dal portale.</p>
@endsection

@section('actions')
    <a href="{{ $bookingUrl }}" class="btn" style="background-color:#1e293b;color:#ffffff;">Prenota ora</a>
@endsection

@section('footer-note')
    Non vuoi più ricevere questi promemoria? <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Disattivali qui</a>.
@endsection
```

- [ ] **Step 5: Crea il controller unsubscribe**

```php
<?php
// app/Http/Controllers/FollowUpReminderUnsubscribeController.php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowUpReminderUnsubscribeController extends Controller
{
    public function __invoke(Request $request, User $user): View
    {
        $user->preferences()->firstOrCreate(
            [],
            ['notification_channel' => 'email']
        )->update(['follow_up_reminders_enabled' => false]);

        return view('portal.follow-up-reminders.unsubscribed');
    }
}
```

- [ ] **Step 6: Crea la view di conferma unsubscribe**

Crea la directory `resources/views/portal/follow-up-reminders/` e il file:

```blade
{{-- resources/views/portal/follow-up-reminders/unsubscribed.blade.php --}}
@extends('layouts.app')

@section('title', 'Promemoria disattivati')

@section('content')
<div class="max-w-md mx-auto py-16 text-center">
    <h1 class="font-display text-2xl font-semibold text-gray-950 dark:text-gray-50 mb-4">
        Promemoria disattivati
    </h1>
    <p class="text-gray-500 dark:text-gray-400">
        Non riceverai più promemoria di prenotazione. Puoi riattivare questa preferenza
        in qualsiasi momento dalle impostazioni del portale.
    </p>
</div>
@endsection
```

- [ ] **Step 7: Aggiungi la route in `routes/web.php`**

Trova il blocco delle route firmate esistenti (vicino a `/r/{appointment}/conferma`) e aggiungi:

```php
Route::get('/follow-up-reminders/unsubscribe/{user}', \App\Http\Controllers\FollowUpReminderUnsubscribeController::class)
    ->name('follow-up-reminders.unsubscribe')
    ->middleware('signed');
```

- [ ] **Step 8: Esegui tutti i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php
```

Expected: tutti i test passano.

- [ ] **Step 9: Commit**

```bash
git add app/Mail/FollowUpReminderMail.php \
        resources/views/emails/follow-up-reminder.blade.php \
        app/Http/Controllers/FollowUpReminderUnsubscribeController.php \
        resources/views/portal/follow-up-reminders/unsubscribed.blade.php \
        routes/web.php \
        tests/Feature/FollowUpReminderTest.php
git commit -m "feat: add FollowUpReminderMail and signed unsubscribe route"
```

---

## Task 7: Scheduler

**Files:**
- Modify: `routes/console.php`

- [ ] **Step 1: Aggiorna `routes/console.php`**

Aggiungi i due import all'inizio del file (dopo gli import esistenti):

```php
use App\Jobs\SendFollowUpReminder;
use App\Models\FollowUpReminder;
```

Poi aggiungi i due schedule alla fine del file:

```php
Schedule::call(function () {
    FollowUpReminder::pending()
        ->orderBy('id')
        ->chunkById(100, function ($reminders) {
            foreach ($reminders as $reminder) {
                SendFollowUpReminder::dispatch($reminder->id);
            }
        });
})->everyFiveMinutes()->description('Dispatch due follow-up reminders');

Schedule::call(function () {
    FollowUpReminder::stale()->update(['status' => 'pending', 'processing_at' => null]);
})->hourly()->description('Recover stale follow-up reminders');
```

- [ ] **Step 2: Verifica la sintassi**

```bash
docker-compose run --rm app php artisan schedule:list
```

Expected: nella lista compaiono i due nuovi schedule `Dispatch due follow-up reminders` e `Recover stale follow-up reminders`.

- [ ] **Step 3: Commit**

```bash
git add routes/console.php
git commit -m "feat: schedule follow-up reminder dispatch and stale recovery"
```

---

## Task 8: UI portale clienti

**Files:**
- Modify: `resources/views/portal/settings/index.blade.php`
- Modify: `app/Http/Controllers/Portal/SettingsController.php`

- [ ] **Step 1: Aggiungi la sezione "Comunicazioni" al template delle impostazioni**

In `resources/views/portal/settings/index.blade.php`, dopo il blocco `{{-- Notifiche --}}` (il `</div>` finale della sezione), aggiungi:

```blade
{{-- Comunicazioni --}}
<div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-100 dark:border-gray-800 px-6 py-4">
        <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Comunicazioni</h2>
    </div>
    <div class="p-6">
        @if (session('communications_updated'))
            <div class="mb-5 rounded border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                {{ session('communications_updated') }}
            </div>
        @endif

        <form method="POST" action="{{ route('portal.settings.communications') }}" class="max-w-md space-y-5">
            @csrf
            @method('PATCH')

            <div class="flex items-start gap-3">
                <input type="hidden" name="follow_up_reminders_enabled" value="0">
                <input type="checkbox" id="follow_up_reminders_enabled" name="follow_up_reminders_enabled"
                    value="1"
                    {{ old('follow_up_reminders_enabled', $preferences->follow_up_reminders_enabled) ? 'checked' : '' }}
                    class="mt-0.5 h-4 w-4 rounded border-gray-300 dark:border-gray-600"
                    style="accent-color: var(--color-primary)">
                <div>
                    <label for="follow_up_reminders_enabled" class="block text-sm font-medium text-gray-700 dark:text-gray-300 cursor-pointer">
                        Ricevi promemoria per prenotare un nuovo appuntamento
                    </label>
                    <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">
                        Ti invieremo un promemoria se è passato un po' dal tuo ultimo appuntamento e non hai ancora una nuova prenotazione.
                    </p>
                </div>
            </div>

            <div>
                <button type="submit" class="btn-primary rounded px-5 py-2.5 text-sm font-semibold text-white">
                    Salva preferenze
                </button>
            </div>
        </form>
    </div>
</div>
```

**Nota sul checkbox:** `<input type="hidden" name="follow_up_reminders_enabled" value="0">` prima del checkbox garantisce che il valore `0` venga sempre inviato quando il checkbox è deselezionato.

- [ ] **Step 2: Aggiungi la route in `routes/web.php`**

Nel gruppo delle route del portale (dove ci sono `portal.settings.profile` e `portal.settings.notifications`), aggiungi:

```php
Route::patch('/portal/settings/communications', [SettingsController::class, 'updateCommunications'])->name('portal.settings.communications');
```

- [ ] **Step 3: Aggiungi il metodo `updateCommunications` al controller**

In `app/Http/Controllers/Portal/SettingsController.php`, aggiungi alla fine della classe (prima di `}`):

```php
public function updateCommunications(Request $request): RedirectResponse
{
    $validated = $request->validate([
        'follow_up_reminders_enabled' => ['required', 'boolean'],
    ]);

    $request->user()->preferences()->firstOrCreate(
        [],
        ['notification_channel' => 'email']
    )->update($validated);

    return back()->with('communications_updated', 'Preferenze aggiornate.');
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/views/portal/settings/index.blade.php \
        app/Http/Controllers/Portal/SettingsController.php \
        routes/web.php
git commit -m "feat: add follow-up reminder toggle to portal settings"
```

---

## Task 9: Filament admin settings

**Files:**
- Modify: `app/Filament/Pages/SystemSettings.php`

- [ ] **Step 1: Aggiungi i campi al metodo `mount()`**

In `app/Filament/Pages/SystemSettings.php`, nel metodo `mount()`, aggiungi alla fine dell'array passato a `$this->form->fill([...])`:

```php
'follow_up_reminders_enabled' => $setting->follow_up_reminders_enabled ?? false,
'follow_up_reminder_days'     => $setting->follow_up_reminder_days ?? 30,
```

- [ ] **Step 2: Aggiungi la sezione al metodo `form()`**

Nell'array `->schema([...])` del metodo `form()`, aggiungi una nuova sezione alla fine (prima del `])`):

```php
Section::make('Promemoria di follow-up')
    ->columns(2)
    ->schema([
        Toggle::make('follow_up_reminders_enabled')
            ->label('Abilita promemoria di follow-up')
            ->helperText('Invia un\'email ai clienti che non prenotano entro N giorni dall\'ultimo appuntamento')
            ->live()
            ->columnSpanFull(),

        TextInput::make('follow_up_reminder_days')
            ->label('Giorni dopo l\'ultimo appuntamento')
            ->helperText('Quanti giorni devono passare prima di inviare il promemoria')
            ->integer()
            ->minValue(7)
            ->maxValue(365)
            ->required()
            ->suffix('giorni')
            ->visible(fn (Get $get): bool => (bool) $get('follow_up_reminders_enabled')),
    ]),
```

Il metodo `save()` già fa `SystemSetting::current()->update($data)` — nessuna modifica necessaria lì.

- [ ] **Step 3: Verifica che la pagina carichi senza errori**

```bash
# Apri http://salone.localhost/admin/system-settings nel browser
# Verifica che la sezione "Promemoria di follow-up" appaia in fondo alla pagina
# Attiva il toggle e verifica che compaia il campo "Giorni dopo l'ultimo appuntamento"
# Salva e verifica la notifica di successo
```

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php
git commit -m "feat: add follow-up reminder section to admin system settings"
```

---

## Task 10: Test suite completa

**Files:**
- Modify: `tests/Feature/FollowUpReminderTest.php`

- [ ] **Step 1: Esegui l'intera suite di test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/FollowUpReminderTest.php --verbose
```

Expected: tutti i 15 test passano.

- [ ] **Step 2: Esegui la suite completa per verificare nessuna regressione**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: tutti i test esistenti continuano a passare.

- [ ] **Step 3: Commit finale**

```bash
git add tests/Feature/FollowUpReminderTest.php
git commit -m "test: complete follow-up reminder test coverage"
```
