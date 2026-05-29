# SaaS Billing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere abbonamento SaaS al gestionale: piano unico €29/mese, 14 giorni di prova gratuita senza carta, cancellazione in qualsiasi momento, monitoraggio nel panel super-admin.

**Architecture:** Laravel Cashier (`laravel/cashier`) aggiunge il trait `Billable` al model `Business` (root multi-tenant). Il trial è tracciato localmente con `trial_ends_at` (nessuna subscription Stripe durante il trial — nessuna carta richiesta). Stripe Checkout hosted gestisce il pagamento. `CheckSubscription` middleware protegge `/admin/*`. Il panel `/superadmin` viene esteso con colonne billing e widget di riepilogo MRR.

**Tech Stack:** Laravel Cashier v15, Stripe Checkout Sessions, Filament 4 custom Page + StatsOverviewWidget

---

## File Structure

**New files:**
- `database/migrations/YYYY_MM_DD_add_cashier_columns_to_businesses_table.php`
- `app/Http/Middleware/CheckSubscription.php`
- `app/Filament/Pages/BillingPage.php`
- `resources/views/filament/pages/billing.blade.php`
- `app/Http/Controllers/StripeBillingWebhookController.php`
- `app/Mail/PaymentFailedMail.php`
- `resources/views/emails/payment-failed.blade.php`
- `app/Filament/SuperAdmin/Widgets/BillingOverviewWidget.php`
- `tests/Unit/Models/BusinessAccessTest.php`
- `tests/Feature/Middleware/CheckSubscriptionTest.php`
- `tests/Feature/Filament/BillingPageTest.php`

**Modified files:**
- `app/Models/Business.php` — Billable trait, hasAccess(), subscriptionStatus(), fillable+cast
- `database/factories/BusinessFactory.php` — trial_ends_at, trialExpired() state
- `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php` — set trial_ends_at
- `app/Providers/Filament/AdminPanelProvider.php` — add CheckSubscription to authMiddleware
- `app/Providers/Filament/SuperAdminPanelProvider.php` — register BillingOverviewWidget
- `app/Filament/SuperAdmin/Resources/BusinessResource.php` — billing columns + actions
- `bootstrap/app.php` — CSRF exclusion for billing webhook, middleware alias
- `routes/web.php` — billing webhook route
- `.env.example` — STRIPE_PRICE_ID, STRIPE_BILLING_WEBHOOK_SECRET, CASHIER_MODEL
- `config/cashier.php` — currency=eur, price_id from env (published by Cashier)

---

### Task 1: Install Laravel Cashier + configure

**Files:**
- Modify: `composer.json` (via composer command)
- Create: `config/cashier.php` (published)
- Modify: `.env.example`

- [ ] **Step 1: Install Cashier**

Run inside Docker:
```bash
docker-compose run --rm --no-deps app composer require laravel/cashier:^15.0
```
Expected: `laravel/cashier v15.x.x` installed, no errors.

- [ ] **Step 2: Publish Cashier migrations and config**

```bash
docker-compose run --rm app php artisan vendor:publish --tag=cashier-migrations
docker-compose run --rm --no-deps app php artisan vendor:publish --tag=cashier-config
```
Expected: `config/cashier.php` created, two new migration files appear in `database/migrations/` (`create_subscriptions_table` and `create_subscription_items_table`).

- [ ] **Step 3: Configure config/cashier.php**

In the published `config/cashier.php`, change these two lines:
```php
// Before:
'model' => env('CASHIER_MODEL', config('auth.providers.users.model', \App\Models\User::class)),
'currency' => env('CASHIER_CURRENCY', 'usd'),

// After:
'model' => env('CASHIER_MODEL', \App\Models\Business::class),
'currency' => env('CASHIER_CURRENCY', 'eur'),
```

Then add this key at the end of the returned array (before the closing `];`):
```php
'price_id' => env('STRIPE_PRICE_ID'),
```

- [ ] **Step 4: Update .env.example**

Add these lines to `.env.example` after the existing `STRIPE_WEBHOOK_SECRET=` line:
```
CASHIER_MODEL=App\Models\Business
CASHIER_CURRENCY=eur
STRIPE_PRICE_ID=price_replace_with_real_id
STRIPE_BILLING_WEBHOOK_SECRET=whsec_replace_with_real_secret
```

- [ ] **Step 5: Commit**

```bash
git add composer.json composer.lock config/cashier.php database/migrations/ .env.example
git commit -m "feat: install and configure Laravel Cashier for SaaS billing"
```

---

### Task 2: Migration — Cashier columns on businesses

**Files:**
- Create: `database/migrations/YYYY_MM_DD_HHMMSS_add_cashier_columns_to_businesses_table.php`

- [ ] **Step 1: Generate migration**

```bash
docker-compose run --rm app php artisan make:migration add_cashier_columns_to_businesses_table
```
Expected: new file in `database/migrations/`.

- [ ] **Step 2: Write migration body**

Open the generated file. Replace the entire class body with:

```php
public function up(): void
{
    Schema::table('businesses', function (Blueprint $table) {
        $table->string('stripe_id')->nullable()->index()->after('status');
        $table->string('pm_type')->nullable()->after('stripe_id');
        $table->string('pm_last_four', 4)->nullable()->after('pm_type');
        $table->string('pm_expiration')->nullable()->after('pm_last_four');
        $table->timestamp('trial_ends_at')->nullable()->after('pm_expiration');
    });
}

public function down(): void
{
    Schema::table('businesses', function (Blueprint $table) {
        $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'pm_expiration', 'trial_ends_at']);
    });
}
```

The file header needs these imports:
```php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
```

- [ ] **Step 3: Run all migrations**

```bash
docker-compose run --rm app php artisan migrate
```
Expected: all migrations run cleanly — `subscriptions`, `subscription_items`, and the new `businesses` columns.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "feat: add Cashier billing columns to businesses table"
```

---

### Task 3: Business model + Factory

**Files:**
- Modify: `app/Models/Business.php`
- Modify: `database/factories/BusinessFactory.php`
- Create: `tests/Unit/Models/BusinessAccessTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/Models/BusinessAccessTest.php`:

```php
<?php

use App\Models\Business;

test('hasAccess returns true when trial is active', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDays(5)]);
    expect($business->hasAccess())->toBeTrue();
});

test('hasAccess returns false when trial expired and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->subDay()]);
    expect($business->hasAccess())->toBeFalse();
});

test('hasAccess returns false when trial_ends_at is null', function () {
    $business = Business::factory()->make(['trial_ends_at' => null]);
    expect($business->hasAccess())->toBeFalse();
});

test('subscriptionStatus returns trial when on generic trial', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->addDays(5)]);
    expect($business->subscriptionStatus())->toBe('trial');
});

test('subscriptionStatus returns expired when trial ended and no subscription', function () {
    $business = Business::factory()->make(['trial_ends_at' => now()->subDay()]);
    expect($business->subscriptionStatus())->toBe('expired');
});

test('factory sets trial_ends_at 14 days in future', function () {
    $business = Business::factory()->make();
    expect($business->trial_ends_at)->toBeInstanceOf(\Carbon\Carbon::class);
    expect($business->trial_ends_at->isFuture())->toBeTrue();
    expect((int) $business->trial_ends_at->diffInDays(now()))->toBe(14);
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Unit/Models/BusinessAccessTest.php -v
```
Expected: All 6 fail (methods don't exist, `trial_ends_at` not in factory).

- [ ] **Step 3: Update Business model**

Replace the full contents of `app/Models/Business.php`:

```php
<?php

namespace App\Models;

use App\Enums\BusinessStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

#[Fillable(['name', 'subdomain', 'status', 'trial_ends_at'])]
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory, Billable;

    protected function casts(): array
    {
        return [
            'status'        => BusinessStatus::class,
            'trial_ends_at' => 'datetime',
        ];
    }

    public static function currentId(): int
    {
        if (! app()->bound('current_business_id')) {
            throw new \RuntimeException('No current business context bound.');
        }

        return (int) app('current_business_id');
    }

    public function hasAccess(): bool
    {
        return $this->onGenericTrial() || $this->subscribed('default');
    }

    public function subscriptionStatus(): string
    {
        if ($this->subscribed('default') && ! $this->subscription('default')?->onGracePeriod()) {
            return 'active';
        }
        if ($this->subscription('default')?->onGracePeriod()) {
            return 'grace_period';
        }
        if ($this->onGenericTrial()) {
            return 'trial';
        }
        return 'expired';
    }

    public function users(): HasMany             { return $this->hasMany(User::class); }
    public function services(): HasMany          { return $this->hasMany(Service::class); }
    public function appointments(): HasMany      { return $this->hasMany(Appointment::class); }
    public function systemSetting(): HasOne      { return $this->hasOne(SystemSetting::class); }
    public function salonProfile(): HasOne       { return $this->hasOne(SalonProfile::class); }
    public function integrationSetting(): HasOne { return $this->hasOne(IntegrationSetting::class); }
}
```

- [ ] **Step 4: Update BusinessFactory**

Replace `definition()` and add `trialExpired()` state in `database/factories/BusinessFactory.php`:

```php
public function definition(): array
{
    return [
        'name'          => fake()->company(),
        'subdomain'     => fake()->unique()->lexify('salon-????'),
        'status'        => BusinessStatus::Active,
        'trial_ends_at' => now()->addDays(14),
    ];
}

public function suspended(): static
{
    return $this->state(['status' => BusinessStatus::Suspended]);
}

public function trialExpired(): static
{
    return $this->state(['trial_ends_at' => now()->subDay()]);
}
```

- [ ] **Step 5: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Unit/Models/BusinessAccessTest.php -v
```
Expected: All 6 pass.

- [ ] **Step 6: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass (no regressions).

- [ ] **Step 7: Commit**

```bash
git add app/Models/Business.php database/factories/BusinessFactory.php \
  tests/Unit/Models/BusinessAccessTest.php
git commit -m "feat: add Billable trait, hasAccess(), subscriptionStatus() to Business"
```

---

### Task 4: CheckSubscription middleware

**Files:**
- Create: `app/Http/Middleware/CheckSubscription.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `tests/Feature/Middleware/CheckSubscriptionTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Middleware/CheckSubscriptionTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\User;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'web']);
});

test('allows admin access when business is on trial', function () {
    $business = Business::factory()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertDontRedirect(route('filament.admin.pages.abbonamento'));
});

test('redirects admin to billing when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertRedirect(route('filament.admin.pages.abbonamento'));
});

test('returns 403 for staff when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('staff');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('billing page itself is accessible even when trial expired', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);

    $this->actingAs($user)
        ->get(route('filament.admin.pages.abbonamento'))
        ->assertDontRedirect(route('filament.admin.pages.abbonamento'));
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php -v
```
Expected: FAIL — middleware class doesn't exist.

- [ ] **Step 3: Create middleware**

Create `app/Http/Middleware/CheckSubscription.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Business;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->bound('current_business_id')) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.abbonamento')) {
            return $next($request);
        }

        $business = Business::find(app('current_business_id'));

        if ($business && ! $business->hasAccess()) {
            $user = $request->user();
            if ($user?->isAdmin()) {
                return redirect()->route('filament.admin.pages.abbonamento');
            }
            abort(403, 'Il tuo account è sospeso. Contatta l\'amministratore del salone.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware alias in bootstrap/app.php**

In `bootstrap/app.php`, update `$middleware->alias([...])` to add the new alias:

```php
$middleware->alias([
    'tenant.user'        => \App\Http\Middleware\EnsureUserBelongsToCurrentBusiness::class,
    'tenant.status'      => \App\Http\Middleware\EnforceTenantStatus::class,
    'check.subscription' => \App\Http\Middleware\CheckSubscription::class,
]);
```

- [ ] **Step 5: Add middleware to AdminPanelProvider**

Read `app/Providers/Filament/AdminPanelProvider.php`. Find the `->authMiddleware([...])` array and add `\App\Http\Middleware\CheckSubscription::class` to it:

```php
->authMiddleware([
    Authenticate::class,
    \App\Http\Middleware\CheckSubscription::class,
])
```

- [ ] **Step 6: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Middleware/CheckSubscriptionTest.php -v
```
Expected: All 4 pass.

- [ ] **Step 7: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Middleware/CheckSubscription.php bootstrap/app.php \
  app/Providers/Filament/AdminPanelProvider.php \
  tests/Feature/Middleware/CheckSubscriptionTest.php
git commit -m "feat: add CheckSubscription middleware to admin panel"
```

---

### Task 5: BillingPage (Filament)

**Files:**
- Create: `app/Filament/Pages/BillingPage.php`
- Create: `resources/views/filament/pages/billing.blade.php`
- Create: `tests/Feature/Filament/BillingPageTest.php`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Filament/BillingPageTest.php`:

```php
<?php

use App\Filament\Pages\BillingPage;
use App\Models\Business;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
});

test('billing page renders trial state', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDays(7)]);
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);
    $this->actingAs($user);

    Livewire::test(BillingPage::class)
        ->assertSee('Periodo di prova')
        ->assertSee('Attiva abbonamento');
});

test('billing page renders expired state', function () {
    $business = Business::factory()->trialExpired()->create();
    $user = User::factory()->for($business)->create();
    $user->assignRole('admin');

    app()->instance('current_business_id', $business->id);
    $this->actingAs($user);

    Livewire::test(BillingPage::class)
        ->assertSee('Accesso scaduto')
        ->assertSee('Abbonati ora');
});
```

- [ ] **Step 2: Run tests — verify they fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/BillingPageTest.php -v
```
Expected: FAIL — BillingPage class doesn't exist.

- [ ] **Step 3: Create BillingPage**

Create `app/Filament/Pages/BillingPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\Business;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class BillingPage extends Page
{
    protected static ?string $navigationLabel = 'Abbonamento';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $slug = 'abbonamento';
    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.pages.billing';

    public function getBusiness(): Business
    {
        return Business::find(app('current_business_id'));
    }

    public function mount(): void
    {
        if (request()->query('checkout') === 'success') {
            Notification::make()
                ->title('Abbonamento attivato con successo!')
                ->success()
                ->send();
        }

        if (request()->query('checkout') === 'cancelled') {
            Notification::make()
                ->title('Pagamento annullato.')
                ->warning()
                ->send();
        }
    }

    protected function getHeaderActions(): array
    {
        $business = $this->getBusiness();

        if (! auth()->user()?->isAdmin()) {
            return [];
        }

        $status = $business->subscriptionStatus();

        return match ($status) {
            'trial', 'expired' => [
                Action::make('subscribe')
                    ->label($status === 'trial' ? 'Attiva abbonamento' : 'Abbonati ora — €29/mese')
                    ->color('primary')
                    ->icon('heroicon-o-credit-card')
                    ->action(function () use ($business) {
                        $session = $business->newSubscription('default', config('cashier.price_id'))
                            ->checkout([
                                'success_url' => route('filament.admin.pages.abbonamento') . '?checkout=success',
                                'cancel_url'  => route('filament.admin.pages.abbonamento') . '?checkout=cancelled',
                            ]);
                        $this->redirect($session->url, navigate: false);
                    }),
            ],
            'active' => [
                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription('L\'abbonamento rimarrà attivo fino alla fine del periodo corrente. Sei sicuro?')
                    ->action(function () use ($business) {
                        $business->subscription('default')->cancel();
                        $endsAt = $business->subscription('default')->ends_at?->format('d/m/Y');
                        Notification::make()
                            ->title("Abbonamento annullato. Accesso garantito fino al {$endsAt}.")
                            ->warning()
                            ->send();
                    }),
            ],
            'grace_period' => [
                Action::make('resume')
                    ->label('Riattiva abbonamento')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->action(function () use ($business) {
                        $business->subscription('default')->resume();
                        Notification::make()
                            ->title('Abbonamento riattivato!')
                            ->success()
                            ->send();
                    }),
            ],
            default => [],
        };
    }
}
```

- [ ] **Step 4: Create Blade view**

Create `resources/views/filament/pages/billing.blade.php`:

```blade
<x-filament-panels::page>
    @php
        $business = $this->getBusiness();
        $status = $business->subscriptionStatus();
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Piano GestionalePro</x-slot>

            @if ($status === 'trial')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="warning">Periodo di prova</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Il tuo periodo di prova termina il
                        <strong>{{ $business->trial_ends_at->format('d/m/Y') }}</strong>
                        ({{ (int) $business->trial_ends_at->diffInDays(now()) }} giorni rimasti)
                    </span>
                </div>

            @elseif ($status === 'active')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="success">Piano attivo</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        GestionalePro — €29/mese
                    </span>
                </div>
                @if ($business->pm_last_four)
                    <p class="mt-2 text-sm text-gray-500">
                        Metodo di pagamento: {{ ucfirst($business->pm_type ?? '') }} ••••{{ $business->pm_last_four }}
                    </p>
                @endif

            @elseif ($status === 'grace_period')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="warning">Abbonamento annullato</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Accesso garantito fino al
                        <strong>{{ $business->subscription('default')->ends_at?->format('d/m/Y') }}</strong>
                    </span>
                </div>

            @else
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="danger">Accesso scaduto</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Il periodo di prova è terminato. Abbonati per continuare a usare GestionalePro.
                    </span>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Dettagli piano</x-slot>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">GestionalePro</p>
                    <p class="text-gray-500">Piano unico</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">€29/mese</p>
                    <p class="text-gray-500">IVA esclusa</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">Cancellazione</p>
                    <p class="text-gray-500">In qualsiasi momento</p>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
```

- [ ] **Step 5: Run tests — verify they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Filament/BillingPageTest.php -v
```
Expected: Both pass.

- [ ] **Step 6: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass.

- [ ] **Step 7: Commit**

```bash
git add app/Filament/Pages/BillingPage.php \
  resources/views/filament/pages/billing.blade.php \
  tests/Feature/Filament/BillingPageTest.php
git commit -m "feat: add BillingPage to admin panel with 4 subscription states"
```

---

### Task 6: Billing webhook controller + route

**Files:**
- Create: `app/Http/Controllers/StripeBillingWebhookController.php`
- Create: `app/Mail/PaymentFailedMail.php`
- Create: `resources/views/emails/payment-failed.blade.php`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`

- [ ] **Step 1: Create webhook controller**

Create `app/Http/Controllers/StripeBillingWebhookController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Mail\PaymentFailedMail;
use App\Models\Business;
use Illuminate\Support\Facades\Mail;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Symfony\Component\HttpFoundation\Response;

class StripeBillingWebhookController extends WebhookController
{
    public function handleInvoicePaymentFailed(array $payload): Response
    {
        $customerId = $payload['data']['object']['customer'] ?? null;

        if ($customerId) {
            $business = Business::where('stripe_id', $customerId)->first();

            if ($business) {
                $admin = $business->users()
                    ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
                    ->first();

                if ($admin) {
                    Mail::to($admin->email)->send(new PaymentFailedMail($business));
                }
            }
        }

        return $this->successMethod();
    }
}
```

- [ ] **Step 2: Create PaymentFailedMail**

Create `app/Mail/PaymentFailedMail.php`:

```php
<?php

namespace App\Mail;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Business $business) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[GestionalePro] Pagamento non riuscito — azione richiesta');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-failed');
    }
}
```

- [ ] **Step 3: Create email view**

Create `resources/views/emails/payment-failed.blade.php`:

```html
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#dc2626">Pagamento non riuscito</h2>
    <p>Il pagamento per l'abbonamento GestionalePro del salone
       <strong>{{ $business->name }}</strong> non è andato a buon fine.</p>
    <p>Per aggiornare il metodo di pagamento ed evitare l'interruzione del servizio,
       accedi al pannello e vai su <strong>Abbonamento</strong>.</p>
    <p>Per assistenza rispondi a questa email.</p>
    <p style="color:#6b7280;font-size:12px;margin-top:32px">
        GestionalePro — {{ now()->format('d/m/Y') }}
    </p>
</body>
</html>
```

- [ ] **Step 4: Add route + exclude from CSRF**

In `routes/web.php`, add this line after the existing `stripe.webhook` route:

```php
Route::post('/stripe/billing-webhook', \App\Http\Controllers\StripeBillingWebhookController::class)
    ->name('stripe.billing.webhook');
```

In `bootstrap/app.php`, update `preventRequestForgery(except: [...])` to include the new endpoint:

```php
$middleware->preventRequestForgery(except: [
    'stripe/webhook',
    'stripe/billing-webhook',
]);
```

- [ ] **Step 5: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/StripeBillingWebhookController.php \
  app/Mail/PaymentFailedMail.php \
  resources/views/emails/payment-failed.blade.php \
  routes/web.php bootstrap/app.php
git commit -m "feat: add Stripe billing webhook controller and payment failed email"
```

---

### Task 7: Super Admin — BusinessResource extension

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource.php`
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php`

- [ ] **Step 1: Add billing columns to table**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, add these three columns inside `->columns([...])` after the existing `created_at` column:

```php
TextColumn::make('subscriptionStatus')
    ->label('Abbonamento')
    ->badge()
    ->state(fn (Business $record): string => match ($record->subscriptionStatus()) {
        'trial'        => 'Trial',
        'active'       => 'Attivo',
        'grace_period' => 'Grace period',
        'expired'      => 'Scaduto',
    })
    ->color(fn (string $state): string => match ($state) {
        'Trial'        => 'warning',
        'Attivo'       => 'success',
        'Grace period' => 'warning',
        'Scaduto'      => 'danger',
        default        => 'gray',
    }),

TextColumn::make('trial_ends_at')
    ->label('Fine trial')
    ->dateTime('d/m/Y')
    ->placeholder('—')
    ->sortable()
    ->toggleable(),

TextColumn::make('pm_last_four')
    ->label('Pagamento')
    ->state(fn (Business $record): string => $record->pm_type
        ? ucfirst($record->pm_type) . ' ••••' . $record->pm_last_four
        : '—'
    )
    ->toggleable(),
```

- [ ] **Step 2: Add "Estendi trial" and "Cancella abbonamento" actions**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, add these two actions inside `->actions([...])` before the existing `EditAction::make()`:

```php
Action::make('extendTrial')
    ->label('Estendi trial')
    ->icon('heroicon-o-clock')
    ->color('warning')
    ->visible(fn (Business $record): bool => in_array($record->subscriptionStatus(), ['trial', 'expired']))
    ->form([
        \Filament\Forms\Components\TextInput::make('days')
            ->label('Giorni aggiuntivi')
            ->numeric()
            ->default(14)
            ->minValue(1)
            ->maxValue(365)
            ->required(),
    ])
    ->action(function (Business $record, array $data): void {
        $base = ($record->trial_ends_at && $record->trial_ends_at->isFuture())
            ? $record->trial_ends_at
            : now();
        $record->update(['trial_ends_at' => $base->addDays((int) $data['days'])]);

        Notification::make()
            ->title("Trial esteso di {$data['days']} giorni.")
            ->success()
            ->send();
    }),

Action::make('cancelSubscriptionNow')
    ->label('Cancella abbonamento')
    ->icon('heroicon-o-x-circle')
    ->color('danger')
    ->visible(fn (Business $record): bool => $record->subscriptionStatus() === 'active')
    ->requiresConfirmation()
    ->modalDescription('L\'accesso verrà revocato immediatamente. Usare solo in casi eccezionali.')
    ->action(function (Business $record): void {
        $record->subscription('default')->cancelNow();

        Notification::make()
            ->title('Abbonamento cancellato immediatamente.')
            ->success()
            ->send();
    }),
```

Add this import at the top of the file (after the existing use statements):
```php
use Filament\Notifications\Notification;
```

- [ ] **Step 3: Set trial_ends_at in CreateBusiness**

In `app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php`, update `mutateFormDataBeforeCreate()`:

```php
protected function mutateFormDataBeforeCreate(array $data): array
{
    $this->adminEmail = $data['admin_email'];
    unset($data['admin_email']);
    $data['trial_ends_at'] = now()->addDays(14);
    return $data;
}
```

- [ ] **Step 4: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/BusinessResource.php \
  app/Filament/SuperAdmin/Resources/BusinessResource/Pages/CreateBusiness.php
git commit -m "feat: add billing columns and actions to super-admin BusinessResource"
```

---

### Task 8: Super Admin — BillingOverviewWidget

**Files:**
- Create: `app/Filament/SuperAdmin/Widgets/BillingOverviewWidget.php`
- Modify: `app/Providers/Filament/SuperAdminPanelProvider.php`

- [ ] **Step 1: Create widget**

Create `app/Filament/SuperAdmin/Widgets/BillingOverviewWidget.php`:

```php
<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\Business;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BillingOverviewWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $trialActive = Business::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now())
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))
            ->count();

        $activeSubscriptions = Business::whereHas(
            'subscriptions',
            fn ($q) => $q->where('stripe_status', 'active')
        )->count();

        $mrr = $activeSubscriptions * 29;

        $expired = Business::where(function ($q) {
            $q->whereNull('trial_ends_at')->orWhere('trial_ends_at', '<', now());
        })->whereDoesntHave('subscriptions', fn ($q) => $q->where('stripe_status', 'active'))
          ->count();

        return [
            Stat::make('Trial attivi', $trialActive)
                ->description('Saloni in periodo di prova')
                ->color('warning')
                ->icon('heroicon-o-clock'),

            Stat::make('Abbonamenti attivi', $activeSubscriptions)
                ->description('Saloni con piano attivo')
                ->color('success')
                ->icon('heroicon-o-check-circle'),

            Stat::make('MRR', '€' . number_format($mrr, 0, ',', '.'))
                ->description('Entrate mensili ricorrenti')
                ->color('primary')
                ->icon('heroicon-o-banknotes'),

            Stat::make('Scaduti', $expired)
                ->description('Trial terminato, nessun abbonamento')
                ->color('danger')
                ->icon('heroicon-o-exclamation-circle'),
        ];
    }
}
```

- [ ] **Step 2: Register widget in SuperAdminPanelProvider**

In `app/Providers/Filament/SuperAdminPanelProvider.php`, add `->discoverWidgets()` and `->widgets()` after `->discoverResources(...)`:

```php
->discoverWidgets(
    in: app_path('Filament/SuperAdmin/Widgets'),
    for: 'App\Filament\SuperAdmin\Widgets'
)
->widgets([
    \App\Filament\SuperAdmin\Widgets\BillingOverviewWidget::class,
])
```

- [ ] **Step 3: Run full suite**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: All pass.

- [ ] **Step 4: Commit**

```bash
git add app/Filament/SuperAdmin/Widgets/BillingOverviewWidget.php \
  app/Providers/Filament/SuperAdminPanelProvider.php
git commit -m "feat: add BillingOverviewWidget to super-admin dashboard"
```

---

## Pre-requisiti manuali (eseguire prima di testare in produzione)

1. **Stripe Dashboard:** creare Product "GestionalePro" → Price €29/mese ricorrente → copiare il `price_id` in `.env` come `STRIPE_PRICE_ID`
2. **Stripe Dashboard:** registrare webhook endpoint `/stripe/billing-webhook` → copiare secret in `STRIPE_BILLING_WEBHOOK_SECRET`
3. **Seed esistente:** verificare che il Business di default (id=1, subdomain=salone) abbia `trial_ends_at` impostato — eseguire:
   ```bash
   docker-compose run --rm app php artisan tinker --execute="App\Models\Business::find(1)->update(['trial_ends_at' => now()->addDays(14)]);"
   ```
4. **Test locale webhook:** `stripe listen --forward-to localhost/stripe/billing-webhook`
