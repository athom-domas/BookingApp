# Programma fedeltà a punti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere un programma fedeltà opzionale per salone in cui i clienti accumulano punti sulla spesa e riscattano uno sconto percentuale una tantum applicato dall'admin al completamento di un appuntamento.

**Architecture:** Due nuove tabelle (`loyalty_accounts` saldo denormalizzato + `loyalty_transactions` ledger) scopate per business via `BelongsToBusiness`. Un `LoyaltyService` centralizza accredito/riscatto/storno in transazione DB. L'accredito è agganciato al completamento del pagamento tramite eventi di dominio (`PaymentCompleted`/`PaymentRefunded`) lanciati da `PaymentService` e gestiti da listener `#[ListensTo]` (idioma già usato dal progetto). Il riscatto avviene nel flusso admin di `EditAppointment`. Config in `SystemSetting`, esposizione nel portale cliente.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Pest. Tutti i comandi girano in Docker.

**Convenzioni del repo (rispettarle):**
- Attributo `#[Fillable([...])]`, non proprietà `$fillable`.
- `protected function casts(): array`, non proprietà `$casts`.
- Factory: docblock `/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Foo> */`.
- Model: docblock `/** @use HasFactory<\Database\Factories\FooFactory> */` sulla riga `use HasFactory`.
- Niente commenti se il PERCHÉ è ovvio; niente docstring multilinea.
- `RefreshDatabase` è globale per i Feature test (vedi `tests/Pest.php`); `beforeEach` lega già `current_business_id => 1`, e una migration crea il Business 1, quindi le FK su `business_id = 1` sono valide nei test.
- I ruoli devono esistere prima di `assignRole`: usare `Role::firstOrCreate(['name' => ..., 'guard_name' => 'web'])` in `beforeEach`.

**Comandi:**
- Test completo: `docker-compose run --rm app ./vendor/bin/pest`
- Singolo file: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyServiceTest.php`
- Migrazione: `docker-compose run --rm app php artisan migrate`

---

## File Structure

**Create:**
- `database/migrations/2026_06_05_100000_create_loyalty_tables.php` — tabelle `loyalty_accounts` + `loyalty_transactions`.
- `database/migrations/2026_06_05_100001_add_loyalty_fields_to_system_settings.php` — 4 colonne config.
- `app/Models/LoyaltyAccount.php` — saldo per cliente.
- `app/Models/LoyaltyTransaction.php` — ledger movimenti.
- `database/factories/LoyaltyAccountFactory.php`
- `database/factories/LoyaltyTransactionFactory.php`
- `app/Services/LoyaltyService.php` — accrue/redeem/reverse.
- `app/Events/PaymentCompleted.php`
- `app/Events/PaymentRefunded.php`
- `app/Listeners/CreditLoyaltyPoints.php` — `#[ListensTo(PaymentCompleted::class)]`.
- `app/Listeners/ReverseLoyaltyPoints.php` — `#[ListensTo(PaymentRefunded::class)]`.
- `resources/views/portal/appointments/partials/loyalty-card.blade.php` — card portale.
- Test: `tests/Feature/Loyalty/LoyaltyModelTest.php`, `LoyaltySettingTest.php`, `LoyaltyServiceTest.php`, `LoyaltyAccrualTest.php`, `LoyaltyRedemptionTest.php`, `LoyaltyPortalTest.php`.

**Modify:**
- `app/Models/SystemSetting.php` — Fillable + casts + default di `current()` + getter.
- `app/Services/PaymentService.php` — dispatch eventi in `markPaymentCompleted()` e `refundPayment()`.
- `app/Filament/Pages/SystemSettings.php` — `mount()` fill + sezione "Fedeltà".
- `app/Filament/Resources/AppointmentResource.php` — toggle "Applica sconto fedeltà".
- `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php` — riscatto in `beforeSave()` + `mutateFormDataBeforeSave()`.
- `app/Http/Controllers/Portal/AppointmentController.php` — passa dati fedeltà alla view.
- `resources/views/portal/appointments/index.blade.php` — include la card fedeltà.

---

## Task 1: Tabelle, modelli e factory fedeltà

**Files:**
- Create: `database/migrations/2026_06_05_100000_create_loyalty_tables.php`
- Create: `app/Models/LoyaltyAccount.php`
- Create: `app/Models/LoyaltyTransaction.php`
- Create: `database/factories/LoyaltyAccountFactory.php`
- Create: `database/factories/LoyaltyTransactionFactory.php`
- Test: `tests/Feature/Loyalty/LoyaltyModelTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Feature/Loyalty/LoyaltyModelTest.php`:

```php
<?php

use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\User;

it('crea un account fedeltà con business_id auto e relazione utente', function () {
    $user = User::factory()->create();

    $account = LoyaltyAccount::create(['user_id' => $user->id, 'points' => 0]);

    expect($account->business_id)->toBe(1)
        ->and($account->points)->toBe(0)
        ->and($account->user->id)->toBe($user->id);
});

it('somma le transazioni nel ledger e le collega all account', function () {
    $user = User::factory()->create();
    $account = LoyaltyAccount::create(['user_id' => $user->id, 'points' => 0]);

    LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'type'               => 'earn',
        'points'             => 50,
    ]);
    LoyaltyTransaction::create([
        'loyalty_account_id' => $account->id,
        'type'               => 'redeem',
        'points'             => -20,
    ]);

    expect($account->transactions()->sum('points'))->toBe(30)
        ->and($account->transactions->first()->business_id)->toBe(1);
});
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyModelTest.php`
Expected: FAIL — `Class "App\Models\LoyaltyAccount" not found`.

- [ ] **Step 3: Creare la migration**

Create `database/migrations/2026_06_05_100000_create_loyalty_tables.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points')->default(0);
            $table->timestamps();
            $table->unique(['business_id', 'user_id']);
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('loyalty_account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->integer('points');
            $table->string('description')->nullable();
            $table->timestamps();
            $table->index(['business_id', 'loyalty_account_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_accounts');
    }
};
```

- [ ] **Step 4: Creare il modello LoyaltyAccount**

Create `app/Models/LoyaltyAccount.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['business_id', 'user_id', 'points'])]
class LoyaltyAccount extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyAccountFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LoyaltyTransaction::class);
    }
}
```

- [ ] **Step 5: Creare il modello LoyaltyTransaction**

Create `app/Models/LoyaltyTransaction.php`:

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['business_id', 'loyalty_account_id', 'appointment_id', 'type', 'points', 'description'])]
class LoyaltyTransaction extends Model
{
    /** @use HasFactory<\Database\Factories\LoyaltyTransactionFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return ['points' => 'integer'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LoyaltyAccount::class, 'loyalty_account_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
```

- [ ] **Step 6: Creare le factory**

Create `database/factories/LoyaltyAccountFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyAccount> */
class LoyaltyAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id' => 1,
            'user_id'     => User::factory(),
            'points'      => 0,
        ];
    }
}
```

Create `database/factories/LoyaltyTransactionFactory.php`:

```php
<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LoyaltyTransaction> */
class LoyaltyTransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'        => 1,
            'loyalty_account_id' => LoyaltyAccount::factory(),
            'appointment_id'     => null,
            'type'               => 'earn',
            'points'             => 10,
            'description'        => null,
        ];
    }
}
```

- [ ] **Step 7: Eseguire i test e verificare che passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyModelTest.php`
Expected: PASS (2 passed).

- [ ] **Step 8: Commit**

```bash
git add database/migrations/2026_06_05_100000_create_loyalty_tables.php app/Models/LoyaltyAccount.php app/Models/LoyaltyTransaction.php database/factories/LoyaltyAccountFactory.php database/factories/LoyaltyTransactionFactory.php tests/Feature/Loyalty/LoyaltyModelTest.php
git commit -m "feat: add loyalty accounts and transactions data layer"
```

---

## Task 2: Configurazione fedeltà in SystemSetting

**Files:**
- Create: `database/migrations/2026_06_05_100001_add_loyalty_fields_to_system_settings.php`
- Modify: `app/Models/SystemSetting.php`
- Test: `tests/Feature/Loyalty/LoyaltySettingTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Feature/Loyalty/LoyaltySettingTest.php`:

```php
<?php

use App\Models\SystemSetting;

it('ha defaults fedeltà coerenti', function () {
    expect(SystemSetting::isLoyaltyEnabled())->toBeFalse()
        ->and(SystemSetting::getLoyaltyPointsPerEuro())->toBe(1)
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(100)
        ->and(SystemSetting::getLoyaltyRewardPercentage())->toBe(10);
});

it('legge i valori fedeltà salvati', function () {
    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 2,
        'loyalty_reward_threshold'  => 200,
        'loyalty_reward_percentage' => 15,
    ]);

    expect(SystemSetting::isLoyaltyEnabled())->toBeTrue()
        ->and(SystemSetting::getLoyaltyPointsPerEuro())->toBe(2)
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(200)
        ->and(SystemSetting::getLoyaltyRewardPercentage())->toBe(15);
});
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltySettingTest.php`
Expected: FAIL — `Call to undefined method App\Models\SystemSetting::isLoyaltyEnabled()`.

- [ ] **Step 3: Creare la migration colonne config**

Create `database/migrations/2026_06_05_100001_add_loyalty_fields_to_system_settings.php`:

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
            $table->boolean('loyalty_enabled')->default(false)->after('reviews_enabled');
            $table->unsignedInteger('loyalty_points_per_euro')->default(1)->after('loyalty_enabled');
            $table->unsignedInteger('loyalty_reward_threshold')->default(100)->after('loyalty_points_per_euro');
            $table->unsignedInteger('loyalty_reward_percentage')->default(10)->after('loyalty_reward_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn([
                'loyalty_enabled',
                'loyalty_points_per_euro',
                'loyalty_reward_threshold',
                'loyalty_reward_percentage',
            ]);
        });
    }
};
```

- [ ] **Step 4: Aggiornare il Fillable di SystemSetting**

In `app/Models/SystemSetting.php`, sostituire l'attributo `#[Fillable([...])]` (riga 10-16) con:

```php
#[Fillable([
    'business_id',
    'slot_generation_weeks', 'slot_granularity_minutes', 'timezone',
    'booking_max_days_ahead', 'cancellation_deadline_hours',
    'reminder_count', 'reminder_1_hours', 'reminder_2_hours', 'payment_mode',
    'reviews_enabled',
    'loyalty_enabled', 'loyalty_points_per_euro', 'loyalty_reward_threshold', 'loyalty_reward_percentage',
])]
```

- [ ] **Step 5: Aggiornare i casts**

In `app/Models/SystemSetting.php`, dentro `casts()`, aggiungere dopo `'reviews_enabled' => 'boolean',`:

```php
            'loyalty_enabled'           => 'boolean',
            'loyalty_points_per_euro'   => 'integer',
            'loyalty_reward_threshold'  => 'integer',
            'loyalty_reward_percentage' => 'integer',
```

- [ ] **Step 6: Aggiungere i default ai due array di `current()`**

In `app/Models/SystemSetting.php`, in **entrambi** gli array di default dentro `current()` (quello del ramo `new self([...])` e quello di `firstOrCreate(..., [...])`), aggiungere dopo `'reviews_enabled' => true,`:

```php
                'loyalty_enabled'           => false,
                'loyalty_points_per_euro'   => 1,
                'loyalty_reward_threshold'  => 100,
                'loyalty_reward_percentage' => 10,
```

- [ ] **Step 7: Aggiungere i getter statici**

In `app/Models/SystemSetting.php`, dopo il metodo `getPaymentMode()`, aggiungere:

```php
    public static function isLoyaltyEnabled(): bool
    {
        return self::current()->loyalty_enabled ?? false;
    }

    public static function getLoyaltyPointsPerEuro(): int
    {
        return self::current()->loyalty_points_per_euro ?? 1;
    }

    public static function getLoyaltyRewardThreshold(): int
    {
        return self::current()->loyalty_reward_threshold ?? 100;
    }

    public static function getLoyaltyRewardPercentage(): int
    {
        return self::current()->loyalty_reward_percentage ?? 10;
    }
```

- [ ] **Step 8: Eseguire i test e verificare che passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltySettingTest.php`
Expected: PASS (2 passed).

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_06_05_100001_add_loyalty_fields_to_system_settings.php app/Models/SystemSetting.php tests/Feature/Loyalty/LoyaltySettingTest.php
git commit -m "feat: add loyalty config fields to SystemSetting"
```

---

## Task 3: LoyaltyService (accrue / redeem / reverse)

**Files:**
- Create: `app/Services/LoyaltyService.php`
- Test: `tests/Feature/Loyalty/LoyaltyServiceTest.php`

- [ ] **Step 1: Scrivere i test che falliscono**

Create `tests/Feature/Loyalty/LoyaltyServiceTest.php`:

```php
<?php

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LoyaltyService;

beforeEach(function () {
    $this->service = app(LoyaltyService::class);
    $this->customer = User::factory()->create();
    $this->appointment = Appointment::factory()->create(['user_id' => $this->customer->id]);
});

it('non accredita se il programma è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('accredita floor(amount * ratio) punti e crea una earn', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 49.99);

    $account = LoyaltyAccount::where('user_id', $this->customer->id)->first();
    expect($account->points)->toBe(49)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('è idempotente: non accredita due volte per lo stesso appuntamento', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 50.0);
    $this->service->accrue($this->appointment, 50.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(50)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('non crea transazioni se i punti sono 0', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);

    $this->service->accrue($this->appointment, 0.4);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->exists())->toBeFalse();
});

it('riscatta la percentuale e scala la soglia di punti', function () {
    SystemSetting::current()->update([
        'loyalty_enabled' => true,
        'loyalty_reward_threshold' => 100,
        'loyalty_reward_percentage' => 10,
    ]);
    $account = LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 120]);

    $percentage = $this->service->redeem($this->appointment);

    expect($percentage)->toBe(10)
        ->and($account->fresh()->points)->toBe(20)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100);
});

it('non riscatta se sotto soglia', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_reward_threshold' => 100]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 50]);

    expect($this->service->redeem($this->appointment))->toBe(0);
});

it('storna l accredito di un appuntamento', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->service->accrue($this->appointment, 50.0);

    $this->service->reverse($this->appointment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'reverse')->first()->points)->toBe(-50);
});
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyServiceTest.php`
Expected: FAIL — `Target class [App\Services\LoyaltyService] does not exist`.

- [ ] **Step 3: Implementare il servizio**

Create `app/Services/LoyaltyService.php`:

```php
<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    public function accrue(Appointment $appointment, float $amount): void
    {
        if (! SystemSetting::isLoyaltyEnabled()) {
            return;
        }

        $alreadyEarned = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'earn')
            ->exists();
        if ($alreadyEarned) {
            return;
        }

        $points = (int) floor($amount * SystemSetting::getLoyaltyPointsPerEuro());
        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($appointment, $points) {
            $account = LoyaltyAccount::firstOrCreate(['user_id' => $appointment->user_id]);
            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'appointment_id'     => $appointment->id,
                'type'               => 'earn',
                'points'             => $points,
                'description'        => "Pagamento appuntamento #{$appointment->id}",
            ]);
            $account->increment('points', $points);
        });
    }

    public function redeem(Appointment $appointment): int
    {
        if (! SystemSetting::isLoyaltyEnabled()) {
            return 0;
        }

        $threshold = SystemSetting::getLoyaltyRewardThreshold();
        $account = LoyaltyAccount::where('user_id', $appointment->user_id)->first();
        if (! $account || $account->points < $threshold) {
            return 0;
        }

        DB::transaction(function () use ($account, $appointment, $threshold) {
            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'appointment_id'     => $appointment->id,
                'type'               => 'redeem',
                'points'             => -$threshold,
                'description'        => "Sconto fedeltà appuntamento #{$appointment->id}",
            ]);
            $account->decrement('points', $threshold);
        });

        return SystemSetting::getLoyaltyRewardPercentage();
    }

    public function reverse(Appointment $appointment): void
    {
        $earn = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'earn')
            ->first();
        if (! $earn) {
            return;
        }

        $alreadyReversed = LoyaltyTransaction::where('appointment_id', $appointment->id)
            ->where('type', 'reverse')
            ->exists();
        if ($alreadyReversed) {
            return;
        }

        $account = LoyaltyAccount::find($earn->loyalty_account_id);
        if (! $account) {
            return;
        }

        DB::transaction(function () use ($account, $appointment, $earn) {
            LoyaltyTransaction::create([
                'loyalty_account_id' => $account->id,
                'appointment_id'     => $appointment->id,
                'type'               => 'reverse',
                'points'             => -$earn->points,
                'description'        => "Storno punti appuntamento #{$appointment->id}",
            ]);
            $account->update(['points' => max(0, $account->points - $earn->points)]);
        });
    }
}
```

- [ ] **Step 4: Eseguire i test e verificare che passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyServiceTest.php`
Expected: PASS (7 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/LoyaltyService.php tests/Feature/Loyalty/LoyaltyServiceTest.php
git commit -m "feat: add LoyaltyService for accrue/redeem/reverse"
```

---

## Task 4: Accumulo automatico al completamento del pagamento (eventi + listener)

**Files:**
- Create: `app/Events/PaymentCompleted.php`
- Create: `app/Events/PaymentRefunded.php`
- Create: `app/Listeners/CreditLoyaltyPoints.php`
- Create: `app/Listeners/ReverseLoyaltyPoints.php`
- Modify: `app/Services/PaymentService.php:129-148` (`markPaymentCompleted`) e `app/Services/PaymentService.php:87-105` (`refundPayment`)
- Test: `tests/Feature/Loyalty/LoyaltyAccrualTest.php`

- [ ] **Step 1: Scrivere i test che falliscono**

Create `tests/Feature/Loyalty/LoyaltyAccrualTest.php`:

```php
<?php

use App\Events\PaymentRefunded;
use App\Models\Appointment;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PaymentService;

beforeEach(function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_points_per_euro' => 1]);
    $this->customer = User::factory()->create();
    $this->appointment = Appointment::factory()->create(['user_id' => $this->customer->id, 'status' => 'confirmed']);
});

it('accredita i punti quando un pagamento in salone viene completato', function () {
    app(PaymentService::class)->recordInPersonPayment($this->appointment->id, 'cash', 80.0);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80);
});

it('non raddoppia i punti se il completamento viene rieseguito', function () {
    $service = app(PaymentService::class);
    $service->recordInPersonPayment($this->appointment->id, 'cash', 80.0);

    // forza un secondo evento di completamento sullo stesso pagamento
    $payment = Payment::where('appointment_id', $this->appointment->id)->first();
    \App\Events\PaymentCompleted::dispatch($payment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(80)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->count())->toBe(1);
});

it('storna i punti quando un pagamento completato viene rimborsato', function () {
    app(PaymentService::class)->recordInPersonPayment($this->appointment->id, 'cash', 80.0);
    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    PaymentRefunded::dispatch($payment);

    expect(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(0);
});
```

- [ ] **Step 2: Eseguire i test e verificare che falliscano**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyAccrualTest.php`
Expected: FAIL — `Class "App\Events\PaymentRefunded" not found`.

- [ ] **Step 3: Creare l'evento PaymentCompleted**

Create `app/Events/PaymentCompleted.php`:

```php
<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentCompleted
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
```

- [ ] **Step 4: Creare l'evento PaymentRefunded**

Create `app/Events/PaymentRefunded.php`:

```php
<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PaymentRefunded
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Payment $payment) {}
}
```

- [ ] **Step 5: Creare il listener CreditLoyaltyPoints**

Create `app/Listeners/CreditLoyaltyPoints.php`:

```php
<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Services\LoyaltyService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(PaymentCompleted::class)]
class CreditLoyaltyPoints
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function handle(PaymentCompleted $event): void
    {
        $appointment = $event->payment->appointment;
        if (! $appointment) {
            return;
        }

        $this->loyalty->accrue($appointment, (float) $event->payment->amount);
    }
}
```

- [ ] **Step 6: Creare il listener ReverseLoyaltyPoints**

Create `app/Listeners/ReverseLoyaltyPoints.php`:

```php
<?php

namespace App\Listeners;

use App\Events\PaymentRefunded;
use App\Services\LoyaltyService;
use Illuminate\Events\Attributes\ListensTo;

#[ListensTo(PaymentRefunded::class)]
class ReverseLoyaltyPoints
{
    public function __construct(private readonly LoyaltyService $loyalty) {}

    public function handle(PaymentRefunded $event): void
    {
        $appointment = $event->payment->appointment;
        if (! $appointment) {
            return;
        }

        $this->loyalty->reverse($appointment);
    }
}
```

- [ ] **Step 7: Lanciare gli eventi da PaymentService**

In `app/Services/PaymentService.php`, aggiungere gli `use` in testa al file (dopo gli altri `use`):

```php
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
```

In `refundPayment()`, sostituire il blocco `$payment->update([... 'status' => 'refunded' ...]);` (righe ~99-102) con:

```php
        $payment->update([
            'status' => 'refunded',
            'stripe_response' => $refund->toArray(),
        ]);

        PaymentRefunded::dispatch($payment);
```

In `markPaymentCompleted()`, sostituire il blocco finale (l'attuale `if (! $alreadyCompleted && $payment->payment_method === 'stripe') {...}`, righe ~145-147) con:

```php
        if (! $alreadyCompleted) {
            PaymentCompleted::dispatch($payment);
        }

        if (! $alreadyCompleted && $payment->payment_method === 'stripe') {
            SendAppointmentConfirmation::dispatch($appointment->fresh());
        }
```

- [ ] **Step 8: Eseguire i test e verificare che passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyAccrualTest.php`
Expected: PASS (3 passed).

Nota: i listener `#[ListensTo]` sono auto-scoperti da Laravel (stesso meccanismo di `SendAppointmentNotifications`), nessuna registrazione manuale.

- [ ] **Step 9: Eseguire la suite Payment esistente per non-regressione**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Models/PaymentTest.php tests/Feature/Portal`
Expected: PASS (nessuna regressione).

- [ ] **Step 10: Commit**

```bash
git add app/Events/PaymentCompleted.php app/Events/PaymentRefunded.php app/Listeners/CreditLoyaltyPoints.php app/Listeners/ReverseLoyaltyPoints.php app/Services/PaymentService.php tests/Feature/Loyalty/LoyaltyAccrualTest.php
git commit -m "feat: accrue/reverse loyalty points on payment completion via events"
```

---

## Task 5: Configurazione fedeltà nella pagina admin SystemSettings

**Files:**
- Modify: `app/Filament/Pages/SystemSettings.php`
- Test: `tests/Feature/Loyalty/LoyaltySettingsPageTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Feature/Loyalty/LoyaltySettingsPageTest.php`:

```php
<?php

use App\Filament\Pages\SystemSettings;
use App\Models\Business;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('salva le impostazioni fedeltà dalla pagina admin', function () {
    livewire(SystemSettings::class)
        ->fillForm([
            'loyalty_enabled'           => true,
            'loyalty_points_per_euro'   => 2,
            'loyalty_reward_threshold'  => 150,
            'loyalty_reward_percentage' => 12,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(SystemSetting::isLoyaltyEnabled())->toBeTrue()
        ->and(SystemSetting::getLoyaltyRewardThreshold())->toBe(150);
});
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltySettingsPageTest.php`
Expected: FAIL — il form non contiene i campi fedeltà, quindi `loyalty_enabled` resta `false`.

- [ ] **Step 3: Riempire i campi fedeltà in `mount()`**

In `app/Filament/Pages/SystemSettings.php`, dentro `mount()`, nell'array passato a `$this->form->fill([...])`, aggiungere dopo `'reviews_enabled' => $setting->reviews_enabled ?? true,`:

```php
            'loyalty_enabled'           => $setting->loyalty_enabled ?? false,
            'loyalty_points_per_euro'   => $setting->loyalty_points_per_euro ?? 1,
            'loyalty_reward_threshold'  => $setting->loyalty_reward_threshold ?? 100,
            'loyalty_reward_percentage' => $setting->loyalty_reward_percentage ?? 10,
```

- [ ] **Step 4: Aggiungere la sezione "Fedeltà" al form**

In `app/Filament/Pages/SystemSettings.php`, dentro `form()`, subito dopo la `Section::make('Sito web')->schema([...])` (e prima della chiusura `])->statePath('data')`), aggiungere:

```php
                Section::make('Fedeltà')
                    ->columns(2)
                    ->schema([
                        Toggle::make('loyalty_enabled')
                            ->label('Abilita programma fedeltà')
                            ->helperText('I clienti accumulano punti sulla spesa e sbloccano uno sconto.')
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('loyalty_points_per_euro')
                            ->label('Punti per euro speso')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti/€')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_threshold')
                            ->label('Punti per lo sconto')
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->suffix('punti')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),

                        TextInput::make('loyalty_reward_percentage')
                            ->label('Sconto sbloccato')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->required()
                            ->suffix('%')
                            ->visible(fn (Get $get): bool => (bool) $get('loyalty_enabled')),
                    ]),
```

(Gli import `Toggle`, `TextInput`, `Get`, `Section` sono già presenti nel file.)

- [ ] **Step 5: Eseguire il test e verificare che passi**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltySettingsPageTest.php`
Expected: PASS (1 passed).

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Pages/SystemSettings.php tests/Feature/Loyalty/LoyaltySettingsPageTest.php
git commit -m "feat: add loyalty config section to admin SystemSettings page"
```

---

## Task 6: Riscatto dello sconto nel completamento appuntamento (admin)

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource.php` (sezione "Stato e pagamento", righe ~135-181)
- Modify: `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`
- Test: `tests/Feature/Loyalty/LoyaltyRedemptionTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Feature/Loyalty/LoyaltyRedemptionTest.php`:

```php
<?php

use App\Filament\Resources\AppointmentResource\Pages\EditAppointment;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\LoyaltyAccount;
use App\Models\LoyaltyTransaction;
use App\Models\Payment;
use App\Models\SystemSetting;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    foreach (['appointments.edit', 'appointments.view_all', 'appointments.payments'] as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    $this->business = Business::withoutGlobalScopes()->orderBy('id')->firstOrFail();
    Filament::setTenant($this->business, isQuiet: true);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    SystemSetting::current()->update([
        'loyalty_enabled'           => true,
        'loyalty_points_per_euro'   => 1,
        'loyalty_reward_threshold'  => 100,
        'loyalty_reward_percentage' => 10,
    ]);

    $this->customer = User::factory()->create();
    $this->customer->assignRole(Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']));
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 120]);

    $this->appointment = Appointment::factory()->create([
        'user_id'    => $this->customer->id,
        'staff_id'   => $this->admin->id,
        'status'     => 'confirmed',
        'final_price' => 100,
    ]);
});

it('applica lo sconto fedeltà e scala i punti al completamento', function () {
    livewire(EditAppointment::class, ['record' => $this->appointment->id])
        ->fillForm([
            'status'                  => 'completed',
            'payment_method'          => 'cash',
            'payment_amount'          => 100,
            'apply_loyalty_discount'  => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(90.0)
        ->and(LoyaltyAccount::where('user_id', $this->customer->id)->first()->points)->toBe(110) // 120 - 100 riscatto + 90 accredito
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->first()->points)->toBe(-100)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'earn')->first()->points)->toBe(90);
});

it('non applica sconto se il toggle è spento', function () {
    livewire(EditAppointment::class, ['record' => $this->appointment->id])
        ->fillForm([
            'status'                  => 'completed',
            'payment_method'          => 'cash',
            'payment_amount'          => 100,
            'apply_loyalty_discount'  => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $payment = Payment::where('appointment_id', $this->appointment->id)->first();

    expect((float) $payment->amount)->toBe(100.0)
        ->and(LoyaltyTransaction::where('appointment_id', $this->appointment->id)->where('type', 'redeem')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyRedemptionTest.php`
Expected: FAIL — primo test: `payment->amount` è `100.0`, non `90.0` (il toggle `apply_loyalty_discount` non esiste ancora).

- [ ] **Step 3: Aggiungere il toggle al form di AppointmentResource**

In `app/Filament/Resources/AppointmentResource.php`, assicurarsi che in testa al file sia importato il modello LoyaltyAccount e SystemSetting (aggiungere se mancano, tra gli altri `use`):

```php
use App\Models\LoyaltyAccount;
use App\Models\SystemSetting;
```

Verificare che `Toggle` sia importato; se manca, aggiungere:

```php
use Filament\Forms\Components\Toggle;
```

Dentro la `Section::make('Stato e pagamento')->schema([...])`, subito dopo il campo `TextInput::make('payment_amount')...`, aggiungere:

```php
                        Toggle::make('apply_loyalty_discount')
                            ->label(fn (): string => 'Applica sconto fedeltà ' . SystemSetting::getLoyaltyRewardPercentage() . '% (−' . SystemSetting::getLoyaltyRewardThreshold() . ' punti)')
                            ->default(false)
                            ->dehydrated(true)
                            ->visible(function (Get $get, ?Appointment $record): bool {
                                if (! SystemSetting::isLoyaltyEnabled() || $get('status') !== 'completed') {
                                    return false;
                                }
                                if ((bool) $get('has_completed_payment')) {
                                    return false;
                                }
                                $userId = $get('user_id') ?? $record?->user_id;
                                if (! $userId) {
                                    return false;
                                }
                                $points = LoyaltyAccount::where('user_id', $userId)->value('points') ?? 0;

                                return $points >= SystemSetting::getLoyaltyRewardThreshold();
                            })
                            ->columnSpanFull(),
```

Assicurarsi che `Appointment` sia importato in testa al file (aggiungere `use App\Models\Appointment;` se manca).

- [ ] **Step 4: Applicare il riscatto in EditAppointment**

In `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php`, aggiungere l'`use` del servizio in testa:

```php
use App\Services\LoyaltyService;
```

Sostituire `mutateFormDataBeforeSave()` per scartare anche il nuovo campo:

```php
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['payment_method'], $data['payment_amount'], $data['has_completed_payment'], $data['apply_loyalty_discount']);

        return $data;
    }
```

Sostituire il blocco finale di `beforeSave()` (l'attuale `try { app(PaymentService::class)->recordInPersonPayment(...); } catch (...) {...}`) con:

```php
        $amount = (float) ($data['payment_amount'] ?? 0);

        if (! empty($data['apply_loyalty_discount'])) {
            $percentage = app(LoyaltyService::class)->redeem($this->record);
            if ($percentage > 0) {
                $amount = round($amount * (1 - $percentage / 100), 2);
            }
        }

        try {
            app(PaymentService::class)->recordInPersonPayment(
                $this->record->id,
                $data['payment_method'],
                $amount
            );
        } catch (BookingException $e) {
            Notification::make()
                ->title($e->getMessage())
                ->danger()
                ->send();
            $this->halt();
        }
```

- [ ] **Step 5: Eseguire i test e verificare che passino**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyRedemptionTest.php`
Expected: PASS (2 passed).

- [ ] **Step 6: Eseguire i test esistenti di EditAppointment per non-regressione**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament`
Expected: PASS (nessuna regressione nel flusso di completamento esistente).

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php tests/Feature/Loyalty/LoyaltyRedemptionTest.php
git commit -m "feat: redeem loyalty discount when completing appointment"
```

---

## Task 7: Card fedeltà nel portale cliente

**Files:**
- Modify: `app/Http/Controllers/Portal/AppointmentController.php:27-45` (`index`)
- Create: `resources/views/portal/appointments/partials/loyalty-card.blade.php`
- Modify: `resources/views/portal/appointments/index.blade.php`
- Test: `tests/Feature/Loyalty/LoyaltyPortalTest.php`

- [ ] **Step 1: Scrivere il test che fallisce**

Create `tests/Feature/Loyalty/LoyaltyPortalTest.php`:

```php
<?php

use App\Models\LoyaltyAccount;
use App\Models\SystemSetting;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');
});

it('mostra il saldo punti nel portale quando il programma è attivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => true, 'loyalty_reward_threshold' => 100]);
    LoyaltyAccount::create(['user_id' => $this->customer->id, 'points' => 60]);

    $this->actingAs($this->customer)
        ->get('/portal/appointments')
        ->assertOk()
        ->assertSee('Programma fedeltà')
        ->assertSee('60');
});

it('nasconde la card fedeltà quando il programma è disattivo', function () {
    SystemSetting::current()->update(['loyalty_enabled' => false]);

    $this->actingAs($this->customer)
        ->get('/portal/appointments')
        ->assertOk()
        ->assertDontSee('Programma fedeltà');
});
```

- [ ] **Step 2: Eseguire il test e verificare che fallisca**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyPortalTest.php`
Expected: FAIL — la view non contiene "Programma fedeltà".

- [ ] **Step 3: Passare i dati fedeltà dal controller**

In `app/Http/Controllers/Portal/AppointmentController.php`, aggiungere gli `use` in testa:

```php
use App\Models\LoyaltyAccount;
use App\Models\SystemSetting;
```

In `index()`, sostituire il `return view('portal.appointments.index', [...]);` con:

```php
        $loyaltyEnabled = SystemSetting::isLoyaltyEnabled();
        $loyaltyPoints = $loyaltyEnabled
            ? (LoyaltyAccount::where('user_id', $request->user()->id)->value('points') ?? 0)
            : 0;

        return view('portal.appointments.index', [
            'upcomingAppointments' => $appointments->filter(fn (Appointment $appointment) => $appointment->isUpcoming())->values(),
            'pastAppointments'     => $appointments->filter(fn (Appointment $appointment) => $appointment->isPast())->sortByDesc('scheduled_date')->values(),
            'waitlistEntries'      => $waitlistEntries,
            'loyaltyEnabled'       => $loyaltyEnabled,
            'loyaltyPoints'        => (int) $loyaltyPoints,
            'loyaltyThreshold'     => SystemSetting::getLoyaltyRewardThreshold(),
            'loyaltyPercentage'    => SystemSetting::getLoyaltyRewardPercentage(),
        ]);
```

- [ ] **Step 4: Creare il partial della card**

Create `resources/views/portal/appointments/partials/loyalty-card.blade.php`:

```blade
@php
    $reached = $loyaltyPoints >= $loyaltyThreshold;
    $progress = $loyaltyThreshold > 0 ? min(100, (int) round($loyaltyPoints / $loyaltyThreshold * 100)) : 0;
@endphp
<section class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Programma fedeltà</h2>
    </div>
    <div class="px-5 py-5 space-y-4">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-3xl font-semibold text-gray-950 dark:text-gray-50">{{ $loyaltyPoints }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">punti accumulati</p>
            </div>
            @if ($reached)
                <span class="rounded-full bg-green-100 dark:bg-green-900/40 px-3 py-1 text-sm font-semibold text-green-700 dark:text-green-300">
                    Sconto {{ $loyaltyPercentage }}% disponibile
                </span>
            @endif
        </div>

        <div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $progress }}%"></div>
            </div>
            @if ($reached)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Lo sconto verrà applicato in salone al tuo prossimo appuntamento.</p>
            @else
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ti mancano {{ $loyaltyThreshold - $loyaltyPoints }} punti per uno sconto del {{ $loyaltyPercentage }}%.</p>
            @endif
        </div>
    </div>
</section>
```

- [ ] **Step 5: Includere la card nella index del portale**

In `resources/views/portal/appointments/index.blade.php`, subito dopo l'header `<div class="flex flex-col justify-between ...">...</div>` di chiusura (cioè dopo il blocco titolo + bottone "Nuova prenotazione", e prima delle liste appuntamenti), aggiungere:

```blade
        @if ($loyaltyEnabled)
            @include('portal.appointments.partials.loyalty-card')
        @endif
```

- [ ] **Step 6: Eseguire il test e verificare che passi**

Run: `docker-compose run --rm app ./vendor/bin/pest tests/Feature/Loyalty/LoyaltyPortalTest.php`
Expected: PASS (2 passed).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Portal/AppointmentController.php resources/views/portal/appointments/partials/loyalty-card.blade.php resources/views/portal/appointments/index.blade.php tests/Feature/Loyalty/LoyaltyPortalTest.php
git commit -m "feat: show loyalty card in customer portal"
```

---

## Task 8: Verifica finale dell'intera feature

**Files:** nessuna modifica (solo verifica).

- [ ] **Step 1: Eseguire l'intera suite di test**

Run: `docker-compose run --rm app ./vendor/bin/pest`
Expected: PASS (tutta la suite verde, inclusi i nuovi `tests/Feature/Loyalty/*`).

- [ ] **Step 2: Eseguire le migrazioni su DB pulito per validare up/down**

Run: `docker-compose run --rm app php artisan migrate:fresh`
Expected: tutte le migrazioni applicate senza errori, incluse `create_loyalty_tables` e `add_loyalty_fields_to_system_settings`.

- [ ] **Step 3: Commit finale (se ci sono residui, es. lockfile o cache)**

```bash
git status
# se nulla da committare, la feature è completa
```

---

## Note operative

- **Contesto multi-tenant negli eventi:** l'accredito legge le impostazioni via `SystemSetting::isLoyaltyEnabled()`, che usa `Business::currentId()`. È valido nei flussi che bindano `current_business_id` (admin in salone e conferma pagamento online dal portale autenticato). Un completamento via webhook Stripe puro senza contesto business non accrediterebbe: è una limitazione nota, accettata per questa iterazione (lo spec considera il riscatto solo nel flusso admin).
- **Voucher consumato non ripristinato:** lo storno (`reverse`) annulla solo l'accredito dell'appuntamento; uno sconto già riscattato resta consumato (semplificazione approvata nello spec).
