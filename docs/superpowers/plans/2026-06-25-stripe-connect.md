# Stripe Connect — Infrastruttura Pagamenti per Salone

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ogni salone che usa BookingApp riceve pagamenti online direttamente sul proprio Stripe account tramite destination charges; BookingApp trattiene una platform fee automatica su ogni transazione.

**Architecture:** `StripeConnectAccount` (1:1 con Business) traccia stato e capabilities del connected account. Il `PaymentService` crea destination charges usando il platform Stripe key (`STRIPE_SECRET_KEY`) con `on_behalf_of` + `application_fee_amount` + `transfer_data.destination`. I webhook sono separati: `/stripe/webhook` per eventi pagamento, `/stripe/connect/webhook` per `account.updated` degli account connessi.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Stripe PHP SDK (`stripe/stripe-php`), Pest

## Global Constraints

- Tutti i comandi vanno eseguiti dentro Docker: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest`
- Modelli usano `#[Fillable([...])]` e `protected function casts(): array` — non `$fillable`/`$casts`
- Factory richiedono docblock `/** @extends Factory<Model> */`; model richiedono `/** @use HasFactory<Factory> */` su `use HasFactory`
- `Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web'])` nei `beforeEach` che usano ruoli
- `RefreshDatabase` è globale — non aggiungerlo nelle singole classi test
- Il platform Stripe key è `config('services.stripe.secret')` (env `STRIPE_SECRET_KEY`) — NON `IntegrationSetting::getStripeSecretKey()`
- Nessun fallback sull'account Stripe BookingApp: se il salone non ha un connected account active, il pagamento online è assente
- `payment_intent.succeeded` arriva su `/stripe/webhook` (platform), NON sul Connect webhook
- Idempotenza webhook: dedup sempre su `stripe_webhook_events.event_id`

---

## File Structure

**Nuovi file:**
- `database/migrations/2026_06_25_200001_create_stripe_connect_accounts_table.php`
- `database/migrations/2026_06_25_200002_create_stripe_webhook_events_table.php`
- `database/migrations/2026_06_25_200003_create_stripe_refunds_table.php`
- `database/migrations/2026_06_25_200004_add_connect_columns_to_payments_table.php`
- `database/migrations/2026_06_25_200005_add_platform_fee_to_businesses_table.php`
- `app/Models/StripeConnectAccount.php` — stato connected account per business
- `app/Models/StripeWebhookEvent.php` — dedup idempotenza
- `app/Models/StripeRefund.php` — audit rimborsi Stripe
- `database/factories/StripeConnectAccountFactory.php`
- `app/Services/StripeConnectService.php` — crea account, genera AccountLink, sync da Stripe, calcola fee
- `app/Http/Controllers/StripeConnectController.php` — onboarding routes
- `app/Http/Controllers/StripeConnectWebhookController.php` — account.updated handler
- `app/Filament/Pages/StripeConnectPage.php` — UI salone con 6 stati
- `app/Filament/Pages/StripeConnectAdminPage.php` — super-admin overview
- `app/Services/RefundService.php` — rimborso con reverse_transfer
- `tests/Feature/Services/StripeConnectServiceTest.php`
- `tests/Feature/Http/StripeConnectWebhookTest.php`
- `tests/Feature/Services/RefundServiceTest.php`

**File modificati:**
- `app/Models/Business.php` — aggiunge `canAcceptOnlinePayments()`, `stripeConnectAccount()`, `stripe_platform_fee_percent` fillable
- `app/Models/Payment.php` — aggiunge campi Connect fillable
- `app/Providers/AppServiceProvider.php` — aggiunge binding `platform.stripe`
- `app/Services/PaymentService.php` — destination charges + fee storage
- `app/Http/Controllers/Portal/BookingController.php` — rispetta `canAcceptOnlinePayments()`
- `routes/web.php` — nuove rotte onboarding + connect webhook
- `config/services.php` — aggiunge `platform_fee_percent`

---

### Task 1: Migrazioni e modelli base

**Files:**
- Create: `database/migrations/2026_06_25_200001_create_stripe_connect_accounts_table.php`
- Create: `database/migrations/2026_06_25_200002_create_stripe_webhook_events_table.php`
- Create: `database/migrations/2026_06_25_200003_create_stripe_refunds_table.php`
- Create: `database/migrations/2026_06_25_200004_add_connect_columns_to_payments_table.php`
- Create: `database/migrations/2026_06_25_200005_add_platform_fee_to_businesses_table.php`
- Create: `app/Models/StripeConnectAccount.php`
- Create: `app/Models/StripeWebhookEvent.php`
- Create: `app/Models/StripeRefund.php`
- Create: `database/factories/StripeConnectAccountFactory.php`

**Interfaces:**
- Produces: `StripeConnectAccount` con campi `stripe_account_id`, `status`, `charges_enabled`, `payouts_enabled`, `details_submitted`, `capabilities`
- Produces: `StripeWebhookEvent` con `event_id` UNIQUE
- Produces: `StripeRefund` con `stripe_refund_id` UNIQUE, `payment_id` FK
- Produces: `Payment` con nuovi campi `stripe_charge_id`, `stripe_application_fee_id`, `stripe_account_id`, `stripe_transfer_id`, `platform_fee_amount`, `platform_fee_percent`

- [ ] **Step 1: Crea le migrazioni**

```php
// database/migrations/2026_06_25_200001_create_stripe_connect_accounts_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_connect_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('stripe_account_id')->unique()->nullable();
            $table->enum('mode', ['test', 'live'])->default('test');
            $table->enum('status', ['pending', 'active', 'restricted', 'disabled'])->default('pending');
            $table->boolean('charges_enabled')->default(false);
            $table->boolean('payouts_enabled')->default(false);
            $table->boolean('details_submitted')->default(false);
            $table->json('capabilities')->nullable();
            $table->json('requirements_currently_due')->nullable();
            $table->json('requirements_past_due')->nullable();
            $table->string('requirements_disabled_reason')->nullable();
            $table->char('default_currency', 3)->nullable();
            $table->char('country', 2)->nullable();
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('last_webhook_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_connect_accounts');
    }
};
```

```php
// database/migrations/2026_06_25_200002_create_stripe_webhook_events_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('account_id')->nullable();
            $table->string('type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
    }
};
```

```php
// database/migrations/2026_06_25_200003_create_stripe_refunds_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stripe_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('stripe_refund_id')->unique();
            $table->unsignedInteger('amount');
            $table->string('status', 50)->default('pending');
            $table->string('reason')->nullable();
            $table->boolean('refund_application_fee')->default(true);
            $table->boolean('reverse_transfer')->default(true);
            $table->string('stripe_balance_transaction_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stripe_refunds');
    }
};
```

```php
// database/migrations/2026_06_25_200004_add_connect_columns_to_payments_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('stripe_charge_id')->nullable()->after('stripe_transaction_id');
            $table->string('stripe_application_fee_id')->nullable()->after('stripe_charge_id');
            $table->string('stripe_account_id')->nullable()->after('stripe_application_fee_id');
            $table->string('stripe_transfer_id')->nullable()->after('stripe_account_id');
            $table->decimal('platform_fee_percent', 5, 2)->nullable()->after('stripe_transfer_id');
            $table->unsignedInteger('platform_fee_amount')->default(0)->after('platform_fee_percent');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_charge_id', 'stripe_application_fee_id',
                'stripe_account_id', 'stripe_transfer_id',
                'platform_fee_percent', 'platform_fee_amount',
            ]);
        });
    }
};
```

```php
// database/migrations/2026_06_25_200005_add_platform_fee_to_businesses_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->decimal('stripe_platform_fee_percent', 5, 2)->nullable()->after('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('stripe_platform_fee_percent');
        });
    }
};
```

- [ ] **Step 2: Crea i modelli**

```php
// app/Models/StripeConnectAccount.php
<?php
namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'business_id', 'stripe_account_id', 'mode', 'status',
    'charges_enabled', 'payouts_enabled', 'details_submitted',
    'capabilities', 'requirements_currently_due', 'requirements_past_due',
    'requirements_disabled_reason', 'default_currency', 'country',
    'onboarding_completed_at', 'last_webhook_at',
])]
class StripeConnectAccount extends Model
{
    /** @use HasFactory<\Database\Factories\StripeConnectAccountFactory> */
    use BelongsToBusiness, HasFactory;

    protected function casts(): array
    {
        return [
            'charges_enabled'           => 'boolean',
            'payouts_enabled'           => 'boolean',
            'details_submitted'         => 'boolean',
            'capabilities'              => 'array',
            'requirements_currently_due'=> 'array',
            'requirements_past_due'     => 'array',
            'onboarding_completed_at'   => 'datetime',
            'last_webhook_at'           => 'datetime',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && $this->charges_enabled === true
            && $this->stripe_account_id !== null;
    }
}
```

```php
// app/Models/StripeWebhookEvent.php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['event_id', 'account_id', 'type', 'payload', 'processed_at', 'failed_at', 'error_message'])]
class StripeWebhookEvent extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'payload'       => 'array',
            'processed_at'  => 'datetime',
            'failed_at'     => 'datetime',
            'created_at'    => 'datetime',
        ];
    }
}
```

```php
// app/Models/StripeRefund.php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'payment_id', 'stripe_refund_id', 'amount', 'status', 'reason',
    'refund_application_fee', 'reverse_transfer',
    'stripe_balance_transaction_id', 'payload',
])]
class StripeRefund extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'amount'                 => 'integer',
            'refund_application_fee' => 'boolean',
            'reverse_transfer'       => 'boolean',
            'payload'                => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
```

- [ ] **Step 3: Crea la factory per StripeConnectAccount**

```php
// database/factories/StripeConnectAccountFactory.php
<?php
namespace Database\Factories;

use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<\App\Models\StripeConnectAccount> */
class StripeConnectAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'business_id'       => Business::factory(),
            'stripe_account_id' => 'acct_' . fake()->regexify('[A-Za-z0-9]{16}'),
            'mode'              => 'test',
            'status'            => 'active',
            'charges_enabled'   => true,
            'payouts_enabled'   => true,
            'details_submitted' => true,
            'country'           => 'IT',
            'default_currency'  => 'eur',
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status'            => 'pending',
            'charges_enabled'   => false,
            'details_submitted' => false,
            'stripe_account_id' => null,
        ]);
    }

    public function restricted(): static
    {
        return $this->state([
            'status'                 => 'restricted',
            'charges_enabled'        => false,
            'requirements_past_due'  => ['individual.dob.day'],
        ]);
    }
}
```

- [ ] **Step 4: Esegui le migrazioni**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: 5 migrazioni applicate, nessun errore.

- [ ] **Step 5: Scrivi test migrazioni e relazioni**

```php
// tests/Feature/Models/StripeConnectAccountTest.php
<?php
use App\Models\Business;
use App\Models\StripeConnectAccount;

it('ha una relazione business', function () {
    $account = StripeConnectAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);
    expect($account->business->id)->toBe($this->business->id);
});

it('isActive restituisce true solo se status active e charges_enabled true', function () {
    $active = StripeConnectAccount::factory()->create([
        'business_id'  => $this->business->id,
        'status'       => 'active',
        'charges_enabled' => true,
        'stripe_account_id' => 'acct_test',
    ]);
    $restricted = StripeConnectAccount::factory()->restricted()->create([
        'business_id' => Business::factory()->create()->id,
    ]);

    expect($active->isActive())->toBeTrue();
    expect($restricted->isActive())->toBeFalse();
});

it('pending factory ha charges_enabled false', function () {
    $account = StripeConnectAccount::factory()->pending()->make();
    expect($account->charges_enabled)->toBeFalse();
    expect($account->stripe_account_id)->toBeNull();
});
```

- [ ] **Step 6: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/StripeConnectAccountTest.php
```

Expected: 3 tests passed.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_06_25_200001_create_stripe_connect_accounts_table.php \
        database/migrations/2026_06_25_200002_create_stripe_webhook_events_table.php \
        database/migrations/2026_06_25_200003_create_stripe_refunds_table.php \
        database/migrations/2026_06_25_200004_add_connect_columns_to_payments_table.php \
        database/migrations/2026_06_25_200005_add_platform_fee_to_businesses_table.php \
        app/Models/StripeConnectAccount.php \
        app/Models/StripeWebhookEvent.php \
        app/Models/StripeRefund.php \
        database/factories/StripeConnectAccountFactory.php \
        tests/Feature/Models/StripeConnectAccountTest.php
git commit -m "feat(connect): add stripe connect account, webhook events, refunds migrations and models"
```

---

### Task 2: Business model — relazione e helper canAcceptOnlinePayments()

**Files:**
- Modify: `app/Models/Business.php`
- Test: `tests/Feature/Models/BusinessConnectTest.php`

**Interfaces:**
- Consumes: `StripeConnectAccount` (Task 1)
- Produces: `Business::canAcceptOnlinePayments(): bool`, `Business::stripeConnectAccount(): HasOne`

- [ ] **Step 1: Scrivi il test**

```php
// tests/Feature/Models/BusinessConnectTest.php
<?php
use App\Models\Business;
use App\Models\StripeConnectAccount;

it('canAcceptOnlinePayments restituisce false se non esiste connected account', function () {
    expect($this->business->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce false se account pending', function () {
    StripeConnectAccount::factory()->pending()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce false se restricted', function () {
    StripeConnectAccount::factory()->restricted()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeFalse();
});

it('canAcceptOnlinePayments restituisce true se account active e charges_enabled', function () {
    StripeConnectAccount::factory()->create(['business_id' => $this->business->id]);
    expect($this->business->fresh()->canAcceptOnlinePayments())->toBeTrue();
});

it('ha una relazione stripeConnectAccount', function () {
    $account = StripeConnectAccount::factory()->create(['business_id' => $this->business->id]);
    expect($this->business->stripeConnectAccount->id)->toBe($account->id);
});
```

- [ ] **Step 2: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/BusinessConnectTest.php
```

Expected: FAIL — metodi non esistono.

- [ ] **Step 3: Modifica Business model**

Aggiungi in `app/Models/Business.php`:

```php
// import in cima
use App\Models\StripeConnectAccount;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Aggiungi `'stripe_platform_fee_percent'` all'attributo `#[Fillable]` esistente.

Aggiungi i metodi dopo `public function integrationSetting()`:

```php
public function stripeConnectAccount(): HasOne
{
    return $this->hasOne(StripeConnectAccount::class);
}

public function canAcceptOnlinePayments(): bool
{
    $account = $this->stripeConnectAccount;
    return $account !== null && $account->isActive();
}
```

- [ ] **Step 4: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/BusinessConnectTest.php
```

Expected: 5 tests passed.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Business.php tests/Feature/Models/BusinessConnectTest.php
git commit -m "feat(connect): add canAcceptOnlinePayments() helper and stripeConnectAccount relation to Business"
```

---

### Task 3: Platform StripeClient + StripeConnectService

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `config/services.php`
- Create: `app/Services/StripeConnectService.php`
- Test: `tests/Feature/Services/StripeConnectServiceTest.php`

**Interfaces:**
- Consumes: `StripeConnectAccount` (Task 1), `Business::canAcceptOnlinePayments()` (Task 2)
- Produces:
  - `StripeConnectService::createAccount(Business $business): StripeConnectAccount`
  - `StripeConnectService::createAccountLink(StripeConnectAccount $account, string $returnUrl, string $refreshUrl): string` — URL dell'AccountLink
  - `StripeConnectService::syncFromStripe(StripeConnectAccount $account): void`
  - `StripeConnectService::createDashboardLink(StripeConnectAccount $account): string` — URL login link
  - `StripeConnectService::calculatePlatformFee(Business $business, int $amountCents): array` — `['cents' => int, 'percent' => float]`

- [ ] **Step 1: Aggiungi `platform_fee_percent` a config/services.php**

In `config/services.php`, trova il blocco `'stripe'` e aggiungi:

```php
'stripe' => [
    'secret'                        => env('STRIPE_SECRET_KEY'),
    'public'                        => env('STRIPE_PUBLIC_KEY'),
    'webhook_secret'                => env('STRIPE_WEBHOOK_SECRET'),
    'connect_webhook_secret'        => env('STRIPE_CONNECT_WEBHOOK_SECRET'),
    'payment_method_configuration'  => env('STRIPE_PAYMENT_METHOD_CONFIGURATION'),
    'platform_fee_percent'          => env('STRIPE_PLATFORM_FEE_PERCENT', 2.5),
],
```

- [ ] **Step 2: Aggiungi binding `platform.stripe` in AppServiceProvider**

In `app/Providers/AppServiceProvider.php`, dentro `register()` dopo il binding esistente di `StripeClient`:

```php
$this->app->bind('platform.stripe', function () {
    $secret = config('services.stripe.secret');
    if (empty($secret)) {
        return null;
    }
    return new StripeClient($secret);
});
```

- [ ] **Step 3: Scrivi i test**

```php
// tests/Feature/Services/StripeConnectServiceTest.php
<?php
use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Mockery\MockInterface;
use Stripe\Account;
use Stripe\AccountLink;
use Stripe\LoginLink;
use Stripe\StripeClient;

beforeEach(function () {
    $this->makeService = function (MockInterface $mockStripe): StripeConnectService {
        return new StripeConnectService($mockStripe);
    };
});

it('createAccount crea un StripeConnectAccount con stripe_account_id', function () {
    $fakeAccount = Account::constructFrom(['id' => 'acct_test123', 'object' => 'account']);

    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('create')->once()->andReturn($fakeAccount);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    $account = ($this->makeService)($mockStripe)->createAccount($this->business);

    expect($account->stripe_account_id)->toBe('acct_test123');
    expect($account->business_id)->toBe($this->business->id);
    expect($account->status)->toBe('pending');
});

it('createAccount non crea duplicato se già esiste un account per il business', function () {
    $existing = StripeConnectAccount::factory()->pending()->create([
        'business_id'      => $this->business->id,
        'stripe_account_id'=> 'acct_existing',
    ]);

    $fakeAccount = Account::constructFrom(['id' => 'acct_new', 'object' => 'account']);
    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('create')->never();
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    $account = ($this->makeService)($mockStripe)->createAccount($this->business);

    expect($account->id)->toBe($existing->id);
    expect(StripeConnectAccount::where('business_id', $this->business->id)->count())->toBe(1);
});

it('calculatePlatformFee usa override business se presente', function () {
    $this->business->update(['stripe_platform_fee_percent' => 3.0]);
    $mockStripe = Mockery::mock(StripeClient::class);
    $result = ($this->makeService)($mockStripe)->calculatePlatformFee($this->business, 10000);

    expect($result['cents'])->toBe(300);
    expect($result['percent'])->toBe(3.0);
});

it('calculatePlatformFee usa fee globale se business non ha override', function () {
    \App\Models\SystemSetting::current()->update(['stripe_platform_fee_percent' => 2.0]);
    $mockStripe = Mockery::mock(StripeClient::class);
    $result = ($this->makeService)($mockStripe)->calculatePlatformFee($this->business, 10000);

    expect($result['cents'])->toBe(200);
    expect($result['percent'])->toBe(2.0);
});

it('syncFromStripe aggiorna charges_enabled e status', function () {
    $connectAccount = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_sync_test',
    ]);

    $fakeAccount = Account::constructFrom([
        'id'              => 'acct_sync_test',
        'object'          => 'account',
        'charges_enabled' => true,
        'payouts_enabled' => true,
        'details_submitted'=> true,
        'capabilities'    => ['card_payments' => 'active', 'transfers' => 'active'],
        'requirements'    => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
        'default_currency'=> 'eur',
        'country'         => 'IT',
    ]);

    $mockAccounts = Mockery::mock();
    $mockAccounts->shouldReceive('retrieve')->with('acct_sync_test')->andReturn($fakeAccount);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('accounts')->andReturn($mockAccounts);

    ($this->makeService)($mockStripe)->syncFromStripe($connectAccount);

    $connectAccount->refresh();
    expect($connectAccount->charges_enabled)->toBeTrue();
    expect($connectAccount->status)->toBe('active');
    expect($connectAccount->country)->toBe('IT');
});
```

- [ ] **Step 4: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/StripeConnectServiceTest.php
```

Expected: FAIL — StripeConnectService non esiste.

- [ ] **Step 5: Crea StripeConnectService**

```php
// app/Services/StripeConnectService.php
<?php
namespace App\Services;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Models\SystemSetting;
use Stripe\StripeClient;

class StripeConnectService
{
    public function __construct(private readonly ?StripeClient $stripe) {}

    public function createAccount(Business $business): StripeConnectAccount
    {
        $existing = StripeConnectAccount::where('business_id', $business->id)->first();
        if ($existing) {
            return $existing;
        }

        $stripeAccount = $this->stripe->accounts->create([
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers'     => ['requested' => true],
            ],
            'metadata' => ['business_id' => $business->id],
        ]);

        return StripeConnectAccount::create([
            'business_id'      => $business->id,
            'stripe_account_id'=> $stripeAccount->id,
            'mode'             => app()->environment('production') ? 'live' : 'test',
            'status'           => 'pending',
        ]);
    }

    public function createAccountLink(StripeConnectAccount $account, string $returnUrl, string $refreshUrl): string
    {
        $link = $this->stripe->accountLinks->create([
            'account'     => $account->stripe_account_id,
            'refresh_url' => $refreshUrl,
            'return_url'  => $returnUrl,
            'type'        => 'account_onboarding',
        ]);

        return $link->url;
    }

    public function syncFromStripe(StripeConnectAccount $account): void
    {
        $stripeAccount = $this->stripe->accounts->retrieve($account->stripe_account_id);

        $requirements = $stripeAccount->requirements ?? null;
        $status = 'pending';

        if ($stripeAccount->charges_enabled) {
            $status = 'active';
        } elseif (! empty($requirements?->past_due)) {
            $status = 'restricted';
        } elseif ($stripeAccount->details_submitted) {
            $status = 'pending';
        }

        $account->update([
            'status'                     => $status,
            'charges_enabled'            => (bool) $stripeAccount->charges_enabled,
            'payouts_enabled'            => (bool) $stripeAccount->payouts_enabled,
            'details_submitted'          => (bool) $stripeAccount->details_submitted,
            'capabilities'               => $stripeAccount->capabilities ? $stripeAccount->capabilities->toArray() : null,
            'requirements_currently_due' => $requirements?->currently_due ?? [],
            'requirements_past_due'      => $requirements?->past_due ?? [],
            'requirements_disabled_reason'=> $requirements?->disabled_reason,
            'default_currency'           => $stripeAccount->default_currency,
            'country'                    => $stripeAccount->country,
            'last_webhook_at'            => now(),
        ]);
    }

    public function createDashboardLink(StripeConnectAccount $account): string
    {
        $link = $this->stripe->accounts->createLoginLink($account->stripe_account_id);
        return $link->url;
    }

    public function calculatePlatformFee(Business $business, int $amountCents): array
    {
        $percent = $business->stripe_platform_fee_percent
            ?? SystemSetting::current()->stripe_platform_fee_percent
            ?? (float) config('services.stripe.platform_fee_percent', 0);

        $cents = (int) round($amountCents * $percent / 100);

        return ['cents' => $cents, 'percent' => (float) $percent];
    }
}
```

- [ ] **Step 6: Aggiungi `stripe_platform_fee_percent` a SystemSetting**

In `app/Models/SystemSetting.php`, aggiungi `'stripe_platform_fee_percent'` all'attributo `#[Fillable]` e al blocco `defaults` in `firstOrCreate` dentro il metodo `current()`.

Aggiungi il metodo statico:

```php
public static function getStripePlatformFeePercent(): ?float
{
    $v = self::current()->stripe_platform_fee_percent;
    return $v !== null ? (float) $v : null;
}
```

Crea anche la migrazione per il campo:

```php
// database/migrations/2026_06_25_200006_add_stripe_platform_fee_to_system_settings_table.php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->decimal('stripe_platform_fee_percent', 5, 2)->nullable()->after('payment_mode');
        });
    }
    public function down(): void
    {
        Schema::table('system_settings', function (Blueprint $table) {
            $table->dropColumn('stripe_platform_fee_percent');
        });
    }
};
```

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 7: Aggiungi binding StripeConnectService in AppServiceProvider**

```php
$this->app->bind(\App\Services\StripeConnectService::class, function ($app) {
    return new \App\Services\StripeConnectService($app->make('platform.stripe'));
});
```

- [ ] **Step 8: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/StripeConnectServiceTest.php
```

Expected: 5 tests passed.

- [ ] **Step 9: Commit**

```bash
git add app/Services/StripeConnectService.php \
        app/Providers/AppServiceProvider.php \
        app/Models/SystemSetting.php \
        config/services.php \
        database/migrations/2026_06_25_200006_add_stripe_platform_fee_to_system_settings_table.php \
        tests/Feature/Services/StripeConnectServiceTest.php
git commit -m "feat(connect): add StripeConnectService with account creation, sync, fee calculation"
```

---

### Task 4: Onboarding controller + rotte

**Files:**
- Create: `app/Http/Controllers/StripeConnectController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Http/StripeConnectOnboardingTest.php`

**Interfaces:**
- Consumes: `StripeConnectService::createAccount()`, `StripeConnectService::createAccountLink()`, `StripeConnectService::createDashboardLink()` (Task 3)
- Produces: rotte `stripe.connect.start`, `stripe.connect.callback`, `stripe.connect.refresh`, `stripe.connect.dashboard`

- [ ] **Step 1: Scrivi i test**

```php
// tests/Feature/Http/StripeConnectOnboardingTest.php
<?php
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Mockery\MockInterface;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    $this->admin = \App\Models\User::factory()->create(['business_id' => $this->business->id]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);
});

it('redirect a Stripe per avviare onboarding', function () {
    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $account = StripeConnectAccount::factory()->pending()->make(['business_id' => $this->business->id]);
        $mock->shouldReceive('createAccount')->once()->andReturn($account);
        $mock->shouldReceive('createAccountLink')->once()->andReturn('https://connect.stripe.com/onboarding/test');
    });

    $response = $this->get(route('stripe.connect.start'));

    $response->assertRedirect('https://connect.stripe.com/onboarding/test');
});

it('callback aggiorna details_submitted', function () {
    $account = StripeConnectAccount::factory()->pending()->create(['business_id' => $this->business->id]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->once();
    });

    $response = $this->get(route('stripe.connect.callback'));

    $response->assertRedirect();
});

it('redirect non autenticato a login', function () {
    auth()->logout();
    $this->get(route('stripe.connect.start'))->assertRedirect('/login');
});
```

- [ ] **Step 2: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectOnboardingTest.php
```

Expected: FAIL — route non trovate.

- [ ] **Step 3: Crea il controller**

```php
// app/Http/Controllers/StripeConnectController.php
<?php
namespace App\Http\Controllers;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StripeConnectController extends Controller
{
    public function __construct(private readonly StripeConnectService $connectService) {}

    public function start(Request $request): RedirectResponse
    {
        $business = Business::findOrFail(Business::currentId());
        $account  = $this->connectService->createAccount($business);

        $url = $this->connectService->createAccountLink(
            $account,
            route('stripe.connect.callback'),
            route('stripe.connect.refresh'),
        );

        return redirect($url);
    }

    public function callback(Request $request): RedirectResponse
    {
        $account = StripeConnectAccount::where('business_id', Business::currentId())->first();

        if ($account && $account->stripe_account_id) {
            $this->connectService->syncFromStripe($account);
        }

        return redirect()->route('filament.admin.pages.stripe-connect')
            ->with('status', 'Configurazione completata. Stripe sta verificando i tuoi dati.');
    }

    public function refresh(Request $request): RedirectResponse
    {
        return $this->start($request);
    }

    public function dashboardLink(Request $request): RedirectResponse
    {
        $account = StripeConnectAccount::where('business_id', Business::currentId())
            ->whereNotNull('stripe_account_id')
            ->firstOrFail();

        $url = $this->connectService->createDashboardLink($account);

        return redirect($url);
    }
}
```

- [ ] **Step 4: Aggiungi le rotte in routes/web.php**

Individua il gruppo autenticato esistente (quello con `->middleware(['auth'])`) e aggiungi:

```php
// Stripe Connect onboarding
Route::middleware(['auth'])->group(function () {
    Route::get('/stripe/connect/start', [\App\Http\Controllers\StripeConnectController::class, 'start'])
        ->name('stripe.connect.start');
    Route::get('/stripe/connect/callback', [\App\Http\Controllers\StripeConnectController::class, 'callback'])
        ->name('stripe.connect.callback');
    Route::get('/stripe/connect/refresh', [\App\Http\Controllers\StripeConnectController::class, 'refresh'])
        ->name('stripe.connect.refresh');
    Route::get('/stripe/connect/dashboard', [\App\Http\Controllers\StripeConnectController::class, 'dashboardLink'])
        ->name('stripe.connect.dashboard');
});
```

- [ ] **Step 5: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectOnboardingTest.php
```

Expected: 3 tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StripeConnectController.php \
        routes/web.php \
        tests/Feature/Http/StripeConnectOnboardingTest.php
git commit -m "feat(connect): add Stripe Connect onboarding controller and routes"
```

---

### Task 5: Filament UI — pagina StripeConnectPage

**Files:**
- Create: `app/Filament/Pages/StripeConnectPage.php`
- Create: `resources/views/filament/pages/stripe-connect.blade.php`

**Interfaces:**
- Consumes: `StripeConnectAccount` (Task 1), `Business::canAcceptOnlinePayments()` (Task 2), rotte Task 4

- [ ] **Step 1: Crea la Filament Page**

```php
// app/Filament/Pages/StripeConnectPage.php
<?php
namespace App\Filament\Pages;

use App\Models\Business;
use App\Models\StripeConnectAccount;
use Filament\Pages\Page;

class StripeConnectPage extends Page
{
    protected string $view = 'filament.pages.stripe-connect';

    protected static ?string $navigationLabel = 'Pagamenti online';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static string|\UnitEnum|null $navigationGroup = 'Impostazioni';
    protected static ?int $navigationSort = 5;

    public function getConnectAccount(): ?StripeConnectAccount
    {
        return StripeConnectAccount::where('business_id', Business::currentId())->first();
    }

    public function getUiState(): string
    {
        $account = $this->getConnectAccount();

        if (! $account || ! $account->stripe_account_id) {
            return 'not_connected';
        }

        if (! $account->details_submitted) {
            return 'incomplete';
        }

        if ($account->status === 'disabled') {
            return 'disabled';
        }

        if ($account->status === 'restricted') {
            return 'restricted';
        }

        if ($account->charges_enabled) {
            return 'active';
        }

        return 'pending_review';
    }
}
```

- [ ] **Step 2: Crea la view**

```blade
{{-- resources/views/filament/pages/stripe-connect.blade.php --}}
<x-filament-panels::page>
    @php $state = $this->getUiState(); $account = $this->getConnectAccount(); @endphp

    <div class="max-w-2xl space-y-6">

        @if ($state === 'not_connected')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <x-slot name="description">Collega il tuo account Stripe per accettare pagamenti online dai clienti.</x-slot>

                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <x-filament::badge color="gray">Non configurato</x-filament::badge>
                    </div>
                    <ol class="space-y-2 text-sm text-gray-600 dark:text-gray-400 list-decimal list-inside">
                        <li>Clicca "Collega Stripe" e completa la verifica guidata (~5 minuti)</li>
                        <li>Stripe verifica i tuoi dati — di solito poche ore</li>
                        <li>I pagamenti online si attivano automaticamente</li>
                    </ol>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        BookingApp trattiene il {{ number_format(config('services.stripe.platform_fee_percent', 2.5), 1) }}% come commissione su ogni pagamento online.<br>
                        Finché non configuri Stripe, i clienti possono prenotare solo con pagamento in salone.
                    </p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.start') }}" icon="heroicon-o-arrow-right">
                        Collega Stripe
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'incomplete')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="warning">Configurazione incompleta</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Hai avviato la configurazione ma non l'hai completata. Clicca per riprendere dal punto in cui ti sei fermato.</p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.refresh') }}" icon="heroicon-o-arrow-path">
                        Riprendi configurazione
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'pending_review')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="info">In attesa di approvazione</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Hai completato il modulo. Stripe sta verificando i tuoi dati — di solito richiede poche ore.
                        Riceverai una notifica appena i pagamenti online saranno attivi.
                    </p>
                </div>
            </x-filament::section>

        @elseif ($state === 'active')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="success">Attivo</x-filament::badge>
                    <dl class="text-sm space-y-1">
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Account:</dt>
                            <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $account->stripe_account_id }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Commissione piattaforma:</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ number_format(config('services.stripe.platform_fee_percent', 2.5), 1) }}%</dd>
                        </div>
                        @if ($account->onboarding_completed_at)
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Attivo dal:</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $account->onboarding_completed_at->format('d/m/Y') }}</dd>
                        </div>
                        @endif
                    </dl>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.dashboard') }}" icon="heroicon-o-arrow-top-right-on-square" color="gray" outlined>
                        Gestisci account Stripe →
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'restricted')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="danger">Account sospeso</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Stripe richiede ulteriori informazioni per mantenere attivo il tuo account.
                        Clicca per accedere e risolvere i requisiti mancanti.
                    </p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.refresh') }}" icon="heroicon-o-exclamation-triangle" color="danger">
                        Risolvi su Stripe →
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'disabled')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="danger">Account disabilitato</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">L'account Stripe è stato disabilitato. Contatta il supporto BookingApp per assistenza.</p>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
```

- [ ] **Step 3: Verifica manuale**

Avvia il server con `docker-compose up -d`, accedi a `/admin`, naviga a "Impostazioni → Pagamenti online". Verifica che la pagina mostri lo stato "Non configurato" senza errori.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/StripeConnectPage.php \
        resources/views/filament/pages/stripe-connect.blade.php
git commit -m "feat(connect): add Filament StripeConnectPage with 6 progressive states"
```

---

### Task 6: PaymentService — destination charges + fee storage

**Files:**
- Modify: `app/Services/PaymentService.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Services/PaymentServiceTest.php` (aggiungi test)

**Interfaces:**
- Consumes: `StripeConnectService::calculatePlatformFee()` (Task 3), `Business::canAcceptOnlinePayments()` (Task 2)
- Produces: `PaymentService::initiateStripePayment()` aggiornato — accetta `Business $business` come terzo parametro

- [ ] **Step 1: Scrivi i nuovi test**

Aggiungi in fondo a `tests/Feature/Services/PaymentServiceTest.php`:

```php
it('initiateStripePayment aggiunge destination charge params se business ha account attivo', function () {
    $account = \App\Models\StripeConnectAccount::factory()->create([
        'business_id'      => $this->business->id,
        'stripe_account_id'=> 'acct_destination',
    ]);

    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $this->business->update(['stripe_platform_fee_percent' => 5.0]);

    $fakeIntent = PaymentIntent::constructFrom([
        'id' => 'pi_connect_test',
        'object' => 'payment_intent',
        'amount' => 10000,
        'currency' => 'eur',
        'status' => 'requires_payment_method',
        'client_secret' => 'pi_connect_test_secret',
    ]);

    $capturedParams = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')
        ->once()
        ->withArgs(function ($params) use (&$capturedParams) {
            $capturedParams = $params;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $service = new \App\Services\PaymentService(
        $mockStripe,
        app(\App\Services\StripeConnectService::class)
    );
    $payment = $service->initiateStripePayment($appointment->id, 10000, $this->business);

    expect($capturedParams['on_behalf_of'])->toBe('acct_destination');
    expect($capturedParams['transfer_data']['destination'])->toBe('acct_destination');
    expect($capturedParams['application_fee_amount'])->toBe(500);
    expect($payment->platform_fee_amount)->toBe(500);
    expect((float) $payment->platform_fee_percent)->toBe(5.0);
    expect($payment->stripe_account_id)->toBe('acct_destination');
});

it('initiateStripePayment non aggiunge destination params se business non ha account attivo', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);

    $fakeIntent = PaymentIntent::constructFrom([
        'id' => 'pi_no_connect',
        'object' => 'payment_intent',
        'amount' => 5000,
        'currency' => 'eur',
        'status' => 'requires_payment_method',
        'client_secret' => 'secret',
    ]);

    $capturedParams = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')
        ->withArgs(function ($params) use (&$capturedParams) {
            $capturedParams = $params;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $service = new \App\Services\PaymentService(
        $mockStripe,
        app(\App\Services\StripeConnectService::class)
    );
    $payment = $service->initiateStripePayment($appointment->id, 5000, $this->business);

    expect(array_key_exists('on_behalf_of', $capturedParams))->toBeFalse();
    expect(array_key_exists('application_fee_amount', $capturedParams))->toBeFalse();
    expect($payment->platform_fee_amount)->toBe(0);
});
```

- [ ] **Step 2: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter="destination charge"
```

Expected: FAIL.

- [ ] **Step 3: Modifica PaymentService**

Aggiorna il constructor e il metodo `initiateStripePayment`:

```php
// app/Services/PaymentService.php — constructor aggiornato
public function __construct(
    private readonly ?StripeClient $stripe,
    private readonly ?StripeConnectService $connectService = null,
) {}
```

Aggiorna `initiateStripePayment()`:

```php
public function initiateStripePayment(int $appointmentId, int $amountCents, ?Business $business = null): Payment
{
    $appointment = Appointment::findOrFail($appointmentId);
    $business ??= \App\Models\Business::find(app()->bound('current_business_id') ? app('current_business_id') : null);

    $connectAccount = $business?->stripeConnectAccount;
    $hasConnect = $connectAccount && $connectAccount->isActive() && $this->connectService;

    $fee = $hasConnect
        ? $this->connectService->calculatePlatformFee($business, $amountCents)
        : ['cents' => 0, 'percent' => null];

    $pmConfig = config('services.stripe.payment_method_configuration');
    $intentParams = [
        'amount'   => $amountCents,
        'currency' => 'eur',
        'metadata' => [
            'appointment_id' => $appointmentId,
            'business_id'    => $business?->id,
        ],
    ];

    if ($hasConnect) {
        $intentParams['on_behalf_of']          = $connectAccount->stripe_account_id;
        $intentParams['application_fee_amount'] = $fee['cents'];
        $intentParams['transfer_data']          = ['destination' => $connectAccount->stripe_account_id];
        $intentParams['automatic_payment_methods'] = ['enabled' => true];
    } elseif ($pmConfig) {
        $intentParams['payment_method_configuration'] = $pmConfig;
    } else {
        $intentParams['automatic_payment_methods'] = ['enabled' => true];
    }

    $paymentIntent = $this->stripe->paymentIntents->create($intentParams);

    $payment = Payment::create([
        'appointment_id'        => $appointmentId,
        'user_id'               => $appointment->user_id,
        'amount'                => $amountCents / 100,
        'status'                => 'pending',
        'payment_method'        => 'stripe',
        'stripe_transaction_id' => $paymentIntent->id,
        'stripe_response'       => $paymentIntent->toArray(),
        'stripe_account_id'     => $hasConnect ? $connectAccount->stripe_account_id : null,
        'platform_fee_amount'   => $fee['cents'],
        'platform_fee_percent'  => $fee['percent'],
    ]);

    return $payment;
}
```

Aggiungi in cima al file: `use App\Models\Business;` e `use App\Services\StripeConnectService;`

Aggiungi `'stripe_account_id', 'platform_fee_amount', 'platform_fee_percent'` alla lista `#[Fillable]` in `Payment.php`.

- [ ] **Step 4: Aggiorna il binding PaymentService in AppServiceProvider**

```php
$this->app->bind(PaymentService::class, function ($app) {
    return new PaymentService(
        $app->make(StripeClient::class),
        $app->make(\App\Services\StripeConnectService::class),
    );
});
```

- [ ] **Step 5: Esegui tutti i test PaymentService**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Expected: tutti i test precedenti + 2 nuovi passano.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php app/Models/Payment.php app/Providers/AppServiceProvider.php \
        tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(connect): PaymentService creates destination charges with platform fee when connected account is active"
```

---

### Task 7: BookingController — nasconde "Paga ora" se account non attivo

**Files:**
- Modify: `app/Http/Controllers/Portal/BookingController.php`
- Test: `tests/Feature/Portal/BookingPortalTest.php` (aggiungi test)

**Interfaces:**
- Consumes: `Business::canAcceptOnlinePayments()` (Task 2)

- [ ] **Step 1: Scrivi il test**

Aggiungi a `tests/Feature/Portal/BookingPortalTest.php`:

```php
it('nasconde pagamento online se il salone non ha account Connect attivo', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $customer = \App\Models\User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');

    // Nessun StripeConnectAccount → canAcceptOnlinePayments() = false
    $response = $this->actingAs($customer)->get('/');

    // Il wizard riceve paymentMode = 'in_salon'
    $response->assertSee('in_salon');
    $response->assertDontSee('online');
});

it('mostra pagamento online se il salone ha account Connect attivo', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    $customer = \App\Models\User::factory()->create(['business_id' => $this->business->id]);
    $customer->assignRole('customer');
    \App\Models\StripeConnectAccount::factory()->create(['business_id' => $this->business->id]);
    \App\Models\SystemSetting::current()->update(['payment_mode' => 'both']);

    $response = $this->actingAs($customer)->get('/');

    $response->assertSee('both');
});
```

- [ ] **Step 2: Individua la riga in BookingController**

In `app/Http/Controllers/Portal/BookingController.php`, riga `'paymentMode' => SystemSetting::getPaymentMode()`, sostituisci con:

```php
'paymentMode' => $this->resolvePaymentMode(),
```

Aggiungi il metodo privato:

```php
private function resolvePaymentMode(): string
{
    $configured = SystemSetting::getPaymentMode();
    if ($configured === 'in_salon') {
        return 'in_salon';
    }
    $business = \App\Models\Business::find(app('current_business_id'));
    if (! $business || ! $business->canAcceptOnlinePayments()) {
        return 'in_salon';
    }
    return $configured;
}
```

- [ ] **Step 3: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Portal/BookingPortalTest.php
```

Expected: tutti passano.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Portal/BookingController.php \
        tests/Feature/Portal/BookingPortalTest.php
git commit -m "feat(connect): hide online payment option when business has no active Stripe Connect account"
```

---

### Task 8: StripeConnectWebhookController — account.updated idempotente

**Files:**
- Create: `app/Http/Controllers/StripeConnectWebhookController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Http/StripeConnectWebhookTest.php`

**Interfaces:**
- Consumes: `StripeConnectService::syncFromStripe()` (Task 3), `StripeWebhookEvent` (Task 1)
- Produces: `POST /stripe/connect/webhook` — gestisce `account.updated` con dedup su `event_id`

- [ ] **Step 1: Scrivi i test**

```php
// tests/Feature/Http/StripeConnectWebhookTest.php
<?php
use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Services\StripeConnectService;
use Illuminate\Support\Facades\Config;
use Mockery\MockInterface;

beforeEach(function () {
    Config::set('services.stripe.connect_webhook_secret', 'whsec_test_connect');
});

function makeConnectWebhookPayload(string $eventId, string $accountId, array $accountData = []): array
{
    return [
        'id'      => $eventId,
        'type'    => 'account.updated',
        'account' => $accountId,
        'data'    => [
            'object' => array_merge([
                'id'              => $accountId,
                'object'          => 'account',
                'charges_enabled' => true,
                'payouts_enabled' => true,
                'details_submitted'=> true,
                'requirements'    => ['currently_due' => [], 'past_due' => [], 'disabled_reason' => null],
                'capabilities'    => [],
                'default_currency'=> 'eur',
                'country'         => 'IT',
            ], $accountData),
        ],
    ];
}

it('elabora account.updated e aggiorna StripeConnectAccount', function () {
    $account = StripeConnectAccount::factory()->pending()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_webhook_test',
    ]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->once();
    });

    $payload = makeConnectWebhookPayload('evt_001', 'acct_webhook_test');

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload, [
            'Stripe-Signature' => 'bypass',
        ]);

    // Webhook controller bypassa signature in test; vedi implementazione
    $response->assertOk();
    expect(StripeWebhookEvent::where('event_id', 'evt_001')->exists())->toBeTrue();
});

it('risponde 200 senza rielaborare un evento già processato (idempotenza)', function () {
    StripeWebhookEvent::create([
        'event_id'     => 'evt_duplicate',
        'type'         => 'account.updated',
        'payload'      => [],
        'processed_at' => now(),
    ]);

    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->never();
    });

    $payload = makeConnectWebhookPayload('evt_duplicate', 'acct_any');

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertOk();
});

it('ignora silenziosamente eventi di account non trovati nel DB', function () {
    $this->mock(StripeConnectService::class, function (MockInterface $mock) {
        $mock->shouldReceive('syncFromStripe')->never();
    });

    $payload = makeConnectWebhookPayload('evt_unknown', 'acct_nonexistent');
    $response = $this->withoutMiddleware()->postJson('/stripe/connect/webhook', $payload);

    $response->assertOk();
});
```

- [ ] **Step 2: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectWebhookTest.php
```

Expected: FAIL — route non trovata.

- [ ] **Step 3: Crea il controller**

```php
// app/Http/Controllers/StripeConnectWebhookController.php
<?php
namespace App\Http\Controllers;

use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Services\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeConnectWebhookController extends Controller
{
    public function __construct(private readonly StripeConnectService $connectService) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.connect_webhook_secret');

        if ($secret) {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('Stripe-Signature', ''),
                    $secret,
                );
            } catch (UnexpectedValueException|SignatureVerificationException) {
                return response()->json(['message' => 'Invalid signature.'], 400);
            }
            $payload  = $event->toArray();
            $eventId  = $event->id;
            $type     = $event->type;
            $accountId = $event->account ?? null;
        } else {
            $payload   = $request->all();
            $eventId   = $payload['id'] ?? null;
            $type      = $payload['type'] ?? null;
            $accountId = $payload['account'] ?? null;
        }

        if (! $eventId) {
            return response()->json(['received' => true]);
        }

        if (StripeWebhookEvent::where('event_id', $eventId)->exists()) {
            return response()->json(['received' => true]);
        }

        StripeWebhookEvent::create([
            'event_id'   => $eventId,
            'account_id' => $accountId,
            'type'       => $type,
            'payload'    => $payload,
        ]);

        if ($type === 'account.updated' && $accountId) {
            $account = StripeConnectAccount::where('stripe_account_id', $accountId)->first();
            if ($account) {
                try {
                    $this->connectService->syncFromStripe($account);
                    StripeWebhookEvent::where('event_id', $eventId)
                        ->update(['processed_at' => now()]);
                } catch (\Throwable $e) {
                    StripeWebhookEvent::where('event_id', $eventId)
                        ->update(['failed_at' => now(), 'error_message' => $e->getMessage()]);
                }
            }
        } else {
            StripeWebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);
        }

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 4: Aggiungi rotta in routes/web.php**

Accanto alla rotta `/stripe/webhook` esistente:

```php
Route::post('/stripe/connect/webhook', \App\Http\Controllers\StripeConnectWebhookController::class)
    ->name('stripe.connect.webhook');
```

Aggiungi la rotta al middleware CSRF exclude se presente. Cerca in `app/Http/Middleware/VerifyCsrfToken.php` o nella configurazione:

```php
// In bootstrap/app.php o VerifyCsrfToken.php
protected $except = [
    'stripe/webhook',
    'stripe/billing-webhook',
    'stripe/connect/webhook', // aggiungi questa
];
```

- [ ] **Step 5: Esegui i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectWebhookTest.php
```

Expected: 3 tests passed.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StripeConnectWebhookController.php \
        routes/web.php \
        tests/Feature/Http/StripeConnectWebhookTest.php
git commit -m "feat(connect): add StripeConnectWebhookController with idempotent account.updated handler"
```

---

### Task 9: RefundService — rimborso con reverse_transfer

**Files:**
- Create: `app/Services/RefundService.php`
- Modify: `app/Services/PaymentService.php` (aggiorna webhook handler per salvare charge_id)
- Test: `tests/Feature/Services/RefundServiceTest.php`

**Interfaces:**
- Consumes: `StripeRefund` (Task 1), `Payment` con nuovi campi (Task 6)
- Produces:
  - `RefundService::refund(Payment $payment, ?int $amountCents = null): StripeRefund`
  - `RefundService::handleExternalRefund(array $chargePayload): void` — per webhook `charge.refunded`

- [ ] **Step 1: Scrivi i test**

```php
// tests/Feature/Services/RefundServiceTest.php
<?php
use App\Events\PaymentRefunded;
use App\Models\Appointment;
use App\Models\Payment;
use App\Models\StripeRefund;
use App\Services\RefundService;
use Illuminate\Support\Facades\Event;
use Mockery\MockInterface;
use Stripe\Refund;
use Stripe\StripeClient;

beforeEach(function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    Event::fake();
    $this->makeService = function (MockInterface $mockStripe): RefundService {
        return new RefundService($mockStripe);
    };
});

it('rimborsa un pagamento completato e crea un StripeRefund record', function () {
    $appointment = Appointment::factory()->create(['status' => 'confirmed']);
    $payment = Payment::factory()->create([
        'appointment_id'   => $appointment->id,
        'status'           => 'completed',
        'payment_method'   => 'stripe',
        'stripe_charge_id' => 'ch_test_refund',
        'stripe_account_id'=> 'acct_test',
        'amount'           => 100.00,
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'     => 're_test_001',
        'object' => 'refund',
        'amount' => 10000,
        'status' => 'succeeded',
        'charge' => 'ch_test_refund',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')
        ->with(Mockery::on(fn($p) =>
            $p['charge'] === 'ch_test_refund'
            && $p['refund_application_fee'] === true
            && $p['reverse_transfer'] === true
        ))
        ->andReturn($fakeRefund);

    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    $refundRecord = ($this->makeService)($mockStripe)->refund($payment);

    expect($refundRecord->stripe_refund_id)->toBe('re_test_001');
    expect($refundRecord->status)->toBe('succeeded');
    expect($refundRecord->refund_application_fee)->toBeTrue();
    expect($refundRecord->reverse_transfer)->toBeTrue();
    expect($payment->fresh()->status)->toBe('refunded');
    Event::assertDispatched(PaymentRefunded::class);
});

it('lancia eccezione se payment non è completed', function () {
    $payment = Payment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn() => ($this->makeService)($mockStripe)->refund($payment))
        ->toThrow(\App\Exceptions\BookingException::class);
});

it('non aggiorna payment.status se Stripe restituisce status pending (saldo insufficiente)', function () {
    $payment = Payment::factory()->create([
        'status'           => 'completed',
        'stripe_charge_id' => 'ch_insufficient',
        'stripe_account_id'=> 'acct_broke',
        'amount'           => 50.00,
    ]);

    $fakeRefund = Refund::constructFrom([
        'id'     => 're_pending_001',
        'object' => 'refund',
        'amount' => 5000,
        'status' => 'pending',
        'charge' => 'ch_insufficient',
    ]);

    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')->andReturn($fakeRefund);
    $mockStripe = Mockery::mock(StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    ($this->makeService)($mockStripe)->refund($payment);

    expect($payment->fresh()->status)->toBe('completed');
    expect(StripeRefund::where('stripe_refund_id', 're_pending_001')->first()->status)->toBe('pending');
});

it('handleExternalRefund sincronizza rimborso arrivato via webhook', function () {
    $payment = Payment::factory()->create([
        'status'           => 'completed',
        'stripe_charge_id' => 'ch_external_refund',
    ]);

    $chargePayload = [
        'id'      => 'ch_external_refund',
        'refunds' => [
            'data' => [[
                'id'     => 're_external_001',
                'amount' => (int)($payment->amount * 100),
                'status' => 'succeeded',
                'charge' => 'ch_external_refund',
                'reason' => null,
            ]],
        ],
    ];

    $mockStripe = Mockery::mock(StripeClient::class);
    ($this->makeService)($mockStripe)->handleExternalRefund($chargePayload);

    expect($payment->fresh()->status)->toBe('refunded');
    expect(StripeRefund::where('stripe_refund_id', 're_external_001')->exists())->toBeTrue();
    Event::assertDispatched(PaymentRefunded::class);
});
```

- [ ] **Step 2: Esegui — verifica che fallisca**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/RefundServiceTest.php
```

Expected: FAIL.

- [ ] **Step 3: Crea RefundService**

```php
// app/Services/RefundService.php
<?php
namespace App\Services;

use App\Events\PaymentRefunded;
use App\Exceptions\BookingException;
use App\Models\Payment;
use App\Models\StripeRefund;
use Stripe\StripeClient;

class RefundService
{
    public function __construct(private readonly ?StripeClient $stripe) {}

    public function refund(Payment $payment, ?int $amountCents = null, bool $refundApplicationFee = true, bool $reverseTransfer = true): StripeRefund
    {
        if ($payment->status !== 'completed') {
            throw new BookingException('Solo i pagamenti completati possono essere rimborsati.');
        }

        $params = [
            'charge'               => $payment->stripe_charge_id,
            'refund_application_fee' => $refundApplicationFee,
            'reverse_transfer'     => $reverseTransfer,
        ];

        if ($amountCents !== null) {
            $params['amount'] = $amountCents;
        }

        $stripeRefund = $this->stripe->refunds->create($params);

        $refundRecord = StripeRefund::create([
            'payment_id'            => $payment->id,
            'stripe_refund_id'      => $stripeRefund->id,
            'amount'                => $stripeRefund->amount,
            'status'                => $stripeRefund->status,
            'reason'                => $stripeRefund->reason,
            'refund_application_fee'=> $refundApplicationFee,
            'reverse_transfer'      => $reverseTransfer,
            'payload'               => $stripeRefund->toArray(),
        ]);

        if ($stripeRefund->status === 'succeeded') {
            $payment->update(['status' => 'refunded']);
            PaymentRefunded::dispatch($payment);
        }

        return $refundRecord;
    }

    public function handleExternalRefund(array $chargePayload): void
    {
        $chargeId = $chargePayload['id'] ?? null;
        if (! $chargeId) {
            return;
        }

        $payment = Payment::where('stripe_charge_id', $chargeId)->first();
        if (! $payment) {
            return;
        }

        $refunds = $chargePayload['refunds']['data'] ?? [];
        foreach ($refunds as $refundData) {
            $refundId = $refundData['id'] ?? null;
            if (! $refundId || StripeRefund::where('stripe_refund_id', $refundId)->exists()) {
                continue;
            }

            StripeRefund::create([
                'payment_id'       => $payment->id,
                'stripe_refund_id' => $refundId,
                'amount'           => $refundData['amount'],
                'status'           => $refundData['status'] ?? 'succeeded',
                'reason'           => $refundData['reason'] ?? null,
                'refund_application_fee' => false,
                'reverse_transfer' => false,
                'payload'          => $refundData,
            ]);
        }

        if ($payment->status !== 'refunded') {
            $payment->update(['status' => 'refunded']);
            PaymentRefunded::dispatch($payment);
        }
    }
}
```

- [ ] **Step 4: Aggiungi `charge.refunded` al webhook platform**

In `app/Services/PaymentService.php`, nel metodo `handleStripeWebhook`, aggiungi dopo il blocco `payment_intent.canceled`:

```php
if ($type === 'payment_intent.succeeded') {
    // Salva charge_id e application_fee_id se presenti nel payload
    $chargeId = $payload['data']['object']['latest_charge'] ?? null;
    $appFeeId = $payload['data']['object']['application_fee_amount'] ?? null;
    if ($chargeId) {
        $payment->update(['stripe_charge_id' => $chargeId]);
    }
    $this->markPaymentCompleted($payment);
}
```

Aggiungi binding RefundService in AppServiceProvider:

```php
$this->app->bind(\App\Services\RefundService::class, function ($app) {
    return new \App\Services\RefundService($app->make('platform.stripe'));
});
```

- [ ] **Step 5: Aggiorna `PaymentService::refundPayment()` per usare RefundService**

Sostituisci il metodo esistente `refundPayment()` in `PaymentService` con:

```php
public function refundPayment(int $paymentId): Payment
{
    $payment = Payment::findOrFail($paymentId);
    app(\App\Services\RefundService::class)->refund($payment);
    return $payment->fresh();
}
```

- [ ] **Step 6: Esegui tutti i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/RefundServiceTest.php tests/Feature/Services/PaymentServiceTest.php
```

Expected: tutti passano.

- [ ] **Step 7: Commit**

```bash
git add app/Services/RefundService.php \
        app/Services/PaymentService.php \
        app/Providers/AppServiceProvider.php \
        tests/Feature/Services/RefundServiceTest.php
git commit -m "feat(connect): add RefundService with reverse_transfer, idempotent external refund sync"
```

---

### Task 10: Super-admin — pagina StripeConnectAdminPage

**Files:**
- Create: `app/Filament/Pages/StripeConnectAdminPage.php`
- Create: `resources/views/filament/pages/stripe-connect-admin.blade.php`

**Interfaces:**
- Consumes: `StripeConnectAccount` (Task 1), `StripeConnectService::syncFromStripe()` (Task 3)

- [ ] **Step 1: Crea la pagina**

```php
// app/Filament/Pages/StripeConnectAdminPage.php
<?php
namespace App\Filament\Pages;

use App\Models\StripeConnectAccount;
use App\Services\StripeConnectService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class StripeConnectAdminPage extends Page
{
    protected string $view = 'filament.pages.stripe-connect-admin';

    protected static ?string $navigationLabel = 'Stripe Connect';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';
    protected static string|\UnitEnum|null $navigationGroup = 'Piattaforma';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getAccounts()
    {
        return StripeConnectAccount::withoutGlobalScopes()->with('business')->latest()->get();
    }

    public function syncAccount(int $id, StripeConnectService $connectService): void
    {
        $account = StripeConnectAccount::withoutGlobalScopes()->findOrFail($id);
        $connectService->syncFromStripe($account);

        Notification::make()
            ->title('Account sincronizzato')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
```

- [ ] **Step 2: Crea la view**

```blade
{{-- resources/views/filament/pages/stripe-connect-admin.blade.php --}}
<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Account connessi">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4">Salone</th>
                            <th class="pb-2 pr-4">Account ID</th>
                            <th class="pb-2 pr-4">Stato</th>
                            <th class="pb-2 pr-4">Charges</th>
                            <th class="pb-2 pr-4">Ultimo webhook</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->getAccounts() as $account)
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                {{ $account->business?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $account->stripe_account_id ?? '—' }}
                            </td>
                            <td class="py-2 pr-4">
                                @php
                                    $color = match($account->status) {
                                        'active'     => 'success',
                                        'restricted' => 'danger',
                                        'disabled'   => 'danger',
                                        default      => 'warning',
                                    };
                                @endphp
                                <x-filament::badge :color="$color">{{ $account->status }}</x-filament::badge>
                            </td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$account->charges_enabled ? 'success' : 'gray'">
                                    {{ $account->charges_enabled ? 'Sì' : 'No' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                {{ $account->last_webhook_at?->diffForHumans() ?? 'Mai' }}
                            </td>
                            <td class="py-2">
                                <button
                                    wire:click="syncAccount({{ $account->id }})"
                                    class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    Sincronizza
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Fee piattaforma globale">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Fee attuale: <strong>{{ config('services.stripe.platform_fee_percent', 2.5) }}%</strong> (env <code>STRIPE_PLATFORM_FEE_PERCENT</code>)<br>
                Sovrascrivibile per singolo salone tramite <code>businesses.stripe_platform_fee_percent</code>.
            </p>
            <p class="text-sm text-gray-500">
                Totale commissioni incassate:
                <strong>€ {{ number_format(\App\Models\Payment::withoutGlobalScopes()->sum('platform_fee_amount') / 100, 2) }}</strong>
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 3: Verifica manuale**

Accedi a `/admin` come superadmin. Verifica che "Piattaforma → Stripe Connect" mostri la tabella degli account (vuota in test).

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/StripeConnectAdminPage.php \
        resources/views/filament/pages/stripe-connect-admin.blade.php
git commit -m "feat(connect): add super-admin StripeConnectAdminPage with account overview and sync"
```

---

## Esegui la suite completa

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: tutti i test precedenti + i nuovi passano, nessuna regressione.
