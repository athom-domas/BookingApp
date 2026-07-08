# Subscription Plans: Base & Plus — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add Base/Plus subscription tiers to gate WhatsApp AI behind the Plus plan, with self-service upgrade from the Billing page.

**Architecture:** A `plan` column on `businesses` caches the subscribed tier. `Business::effectivePlan()` resolves the live plan using Cashier's `subscribedToPrice()` + trial/override logic. A `PlanFeatureGate` service maps feature names to required plans. A Cashier webhook listener keeps the column in sync. The BillingPage is redesigned to show two plan cards with swap actions.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Laravel Cashier (Stripe), Pest

**Spec:** `docs/superpowers/specs/2026-07-08-subscription-plans-design.md`

## Global Constraints

- All tests run in Docker: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest <path>`
- Model attributes use `#[Fillable([...])]` — not `$fillable` property
- Model casts use `protected function casts(): array` — not `$casts` property
- Test role prerequisites: `Role::firstOrCreate(['name' => '...', 'guard_name' => 'web'])` before `assignRole()`
- No PHP locally — all `php artisan`, `composer`, and `pest` commands run inside Docker
- Pest test files use `it()` and `beforeEach()` — not PHPUnit class syntax

---

### Task 1: Migration + config/plans.php

**Files:**
- Create: `database/migrations/2026_07_08_120000_add_plan_to_businesses.php`
- Create: `config/plans.php`
- Modify: `app/Models/Business.php` — `#[Fillable]`, `casts()`
- Modify: `.env` — add `STRIPE_PRICE_ID_BASE`, `STRIPE_PRICE_ID_PLUS`

**Interfaces:**
- Produces: `businesses.plan` enum column (default `base`), `businesses.plan_override` nullable enum, `businesses.plan_override_expires_at` nullable timestamp, `businesses.plan_override_reason` nullable string
- Produces: `config('plans.base.price_id')`, `config('plans.plus.price_id')`, `config('plans.features.*')` — used in every subsequent task

- [ ] **Step 1: Write failing test**

Create `tests/Feature/Models/BusinessPlanColumnsTest.php`:

```php
<?php

use App\Models\Business;
use Illuminate\Support\Facades\Schema;

it('businesses table has plan columns after migration', function () {
    expect(Schema::hasColumn('businesses', 'plan'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override_expires_at'))->toBeTrue();
    expect(Schema::hasColumn('businesses', 'plan_override_reason'))->toBeTrue();
});

it('new business defaults to base plan', function () {
    $business = Business::factory()->create();

    expect($business->plan)->toBe('base');
    expect($business->plan_override)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/BusinessPlanColumnsTest.php
```

Expected: FAIL — column `plan` does not exist.

- [ ] **Step 3: Create migration**

```php
<?php
// database/migrations/2026_07_08_120000_add_plan_to_businesses.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->enum('plan', ['base', 'plus'])->default('base')->after('status');
            $table->enum('plan_override', ['base', 'plus'])->nullable()->after('plan');
            $table->timestamp('plan_override_expires_at')->nullable()->after('plan_override');
            $table->string('plan_override_reason')->nullable()->after('plan_override_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'plan_override',
                'plan_override_expires_at',
                'plan_override_reason',
            ]);
        });
    }
};
```

- [ ] **Step 4: Create config/plans.php**

```php
<?php
// config/plans.php

return [
    'base' => [
        'price_id' => env('STRIPE_PRICE_ID_BASE', env('STRIPE_PRICE_ID')), // fallback to old var
        'label'    => 'Base',
        'price'    => env('PLAN_BASE_PRICE', 29),
        'features' => [
            'Gestione appuntamenti',
            'Notifiche email/SMS',
            'Portale clienti',
            'Google Calendar sync',
        ],
    ],
    'plus' => [
        'price_id' => env('STRIPE_PRICE_ID_PLUS'),
        'label'    => 'Plus',
        'price'    => env('PLAN_PLUS_PRICE'), // null until confirmed
        'features' => [
            'Tutto il piano Base',
            'Assistente AI WhatsApp',
            'Prenotazioni via WhatsApp',
            'Cancellazioni via WhatsApp',
        ],
    ],

    // Feature → required plans. Add new features here — no controller changes needed.
    'features' => [
        'whatsapp_ai'           => ['plus'],
        'whatsapp_booking'      => ['plus'],
        'whatsapp_cancellation' => ['plus'],
    ],
];
```

- [ ] **Step 5: Update Business model**

In `app/Models/Business.php`, update the `#[Fillable]` attribute and `casts()`:

```php
#[Fillable(['name', 'subdomain', 'status', 'trial_ends_at', 'stripe_platform_fee_percent', 'plan', 'plan_override', 'plan_override_expires_at', 'plan_override_reason'])]
```

```php
protected function casts(): array
{
    return [
        'status'                    => BusinessStatus::class,
        'trial_ends_at'             => 'datetime',
        'stripe_platform_fee_percent' => 'float',
        'plan_override_expires_at'  => 'datetime',
    ];
}
```

- [ ] **Step 6: Add .env entries**

Add to `.env` (use the current `STRIPE_PRICE_ID` value for `STRIPE_PRICE_ID_BASE`):

```
STRIPE_PRICE_ID_BASE=price_...   # same value as existing STRIPE_PRICE_ID
STRIPE_PRICE_ID_PLUS=            # fill when Plus price is created in Stripe
PLAN_BASE_PRICE=29
PLAN_PLUS_PRICE=                 # fill before launch
```

- [ ] **Step 7: Run migration**

```bash
docker-compose run --rm app php artisan migrate
```

- [ ] **Step 8: Run test to verify it passes**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Models/BusinessPlanColumnsTest.php
```

Expected: 2 passed.

- [ ] **Step 9: Commit**

```bash
git add database/migrations/2026_07_08_120000_add_plan_to_businesses.php \
        config/plans.php \
        app/Models/Business.php \
        tests/Feature/Models/BusinessPlanColumnsTest.php
git commit -m "feat: add plan columns to businesses + config/plans.php"
```

---

### Task 2: Business::effectivePlan() + PlanFeatureGate

**Files:**
- Modify: `app/Models/Business.php` — add `effectivePlan()`, `hasActivePlanOverride()`, `canUseFeature()`
- Create: `app/Services/PlanFeatureGate.php`
- Create: `tests/Unit/Models/BusinessPlanTest.php`

**Interfaces:**
- Produces: `Business::effectivePlan(): string` — returns `'base'` or `'plus'`
- Produces: `Business::hasPlanOverride(): bool`
- Produces: `Business::canUseFeature(string $feature): bool` — the single public API for all feature gates
- Produces: `PlanFeatureGate::allows(Business $business, string $feature): bool`

- [ ] **Step 1: Write failing tests**

Create `tests/Unit/Models/BusinessPlanTest.php`:

```php
<?php

use App\Models\Business;
use App\Services\PlanFeatureGate;

// --- effectivePlan() ---

it('returns plus during active trial', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);

    expect($business->effectivePlan())->toBe('plus');
});

it('returns base with expired trial and no subscription', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->subDay()]);

    expect($business->effectivePlan())->toBe('base');
});

it('returns base with no trial and no subscription', function () {
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect($business->effectivePlan())->toBe('base');
});

it('returns plus for active plus subscription', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(false);
    $business->shouldReceive('subscribedToPrice')
        ->with(config('plans.plus.price_id'), 'default')
        ->andReturn(true);

    expect($business->effectivePlan())->toBe('plus');
});

it('returns base for active base subscription', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(false);
    $business->shouldReceive('subscribedToPrice')
        ->with(config('plans.plus.price_id'), 'default')
        ->andReturn(false);

    expect($business->effectivePlan())->toBe('base');
});

it('returns base when subscription has incomplete payment', function () {
    $business = Mockery::mock(Business::class)->makePartial();
    $business->plan_override            = null;
    $business->trial_ends_at            = now()->subDay();
    $business->shouldReceive('onGenericTrial')->andReturn(false);
    $business->shouldReceive('subscribed')->with('default')->andReturn(true);
    $business->shouldReceive('hasIncompletePayment')->with('default')->andReturn(true);

    expect($business->effectivePlan())->toBe('base');
});

it('returns override plan when active plan override is set', function () {
    $business = Business::factory()->create([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => null,
    ]);

    expect($business->effectivePlan())->toBe('plus');
});

it('ignores expired plan override and falls through to subscription check', function () {
    $business = Business::factory()->create([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => now()->subDay(),
    ]);

    // No subscription → base
    expect($business->effectivePlan())->toBe('base');
});

// --- canUseFeature() ---

it('trial business can use whatsapp_ai', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeTrue();
});

it('base-plan business cannot use whatsapp_ai', function () {
    $business = Business::factory()->create(['trial_ends_at' => null]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeFalse();
});

it('unknown feature is denied', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);

    expect($business->canUseFeature('nonexistent_feature'))->toBeFalse();
});

it('plus override business can use whatsapp_ai', function () {
    $business = Business::factory()->create([
        'trial_ends_at'           => null,
        'plan_override'           => 'plus',
        'plan_override_expires_at' => null,
    ]);

    expect($business->canUseFeature('whatsapp_ai'))->toBeTrue();
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Unit/Models/BusinessPlanTest.php
```

Expected: FAIL — `effectivePlan` method not found.

- [ ] **Step 3: Create PlanFeatureGate**

Create `app/Services/PlanFeatureGate.php`:

```php
<?php

namespace App\Services;

use App\Models\Business;

class PlanFeatureGate
{
    public function allows(Business $business, string $feature): bool
    {
        $requiredPlans = config("plans.features.{$feature}");

        if ($requiredPlans === null) {
            return false; // unknown feature → deny by default
        }

        return in_array($business->effectivePlan(), $requiredPlans, true);
    }
}
```

- [ ] **Step 4: Add methods to Business model**

In `app/Models/Business.php`, add after `canAcceptOnlinePayments()`:

```php
use App\Services\PlanFeatureGate;

public function effectivePlan(): string
{
    if ($this->hasActivePlanOverride()) {
        return $this->plan_override;
    }

    if ($this->onGenericTrial()) {
        return 'plus';
    }

    if (! $this->subscribed('default')) {
        return 'base';
    }

    if ($this->hasIncompletePayment('default')) {
        return 'base';
    }

    if ($this->subscribedToPrice(config('plans.plus.price_id'), 'default')) {
        return 'plus';
    }

    return 'base';
}

public function hasActivePlanOverride(): bool
{
    return $this->plan_override !== null
        && ($this->plan_override_expires_at === null || $this->plan_override_expires_at->isFuture());
}

public function canUseFeature(string $feature): bool
{
    return app(PlanFeatureGate::class)->allows($this, $feature);
}
```

Also add the `use` import at the top of the file:

```php
use App\Services\PlanFeatureGate;
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Unit/Models/BusinessPlanTest.php
```

Expected: 12 passed.

- [ ] **Step 6: Commit**

```bash
git add app/Models/Business.php \
        app/Services/PlanFeatureGate.php \
        tests/Unit/Models/BusinessPlanTest.php
git commit -m "feat: add effectivePlan/canUseFeature to Business + PlanFeatureGate service"
```

---

### Task 3: WhatsApp webhook + job gating

**Files:**
- Modify: `app/Http/Controllers/WhatsAppWebhookController.php` — plan gate + fallback reply
- Modify: `app/Jobs/ProcessWhatsAppMessageJob.php` — defensive guard
- Create: `tests/Feature/WhatsApp/WebhookPlanGatingTest.php`

**Interfaces:**
- Consumes: `Business::canUseFeature(string $feature)` from Task 2
- Consumes: `WhatsAppService::sendTextWithinWindow(string $phone, string $text, Carbon $lastUserMessageAt, int $businessId): bool`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/WhatsApp/WebhookPlanGatingTest.php`:

```php
<?php

use App\Jobs\ProcessWhatsAppMessageJob;
use App\Models\Business;
use App\Models\IntegrationSetting;
use Illuminate\Support\Facades\Queue;

function whatsappPayload(string $phoneNumberId, string $from, string $text): array
{
    return [
        'object' => 'whatsapp_business_account',
        'entry'  => [[
            'changes' => [[
                'value' => [
                    'metadata'  => ['phone_number_id' => $phoneNumberId],
                    'messages'  => [[
                        'id'   => 'wamid_' . uniqid(),
                        'from' => ltrim($from, '+'),
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                    'contacts' => [[
                        'wa_id'   => ltrim($from, '+'),
                        'profile' => ['name' => 'Test User'],
                    ]],
                ],
            ]],
        ]],
    ];
}

beforeEach(function () {
    Queue::fake();
    config(['services.whatsapp.app_secret' => null]); // skip signature check in tests
});

it('does not dispatch AI job for base-plan business', function () {
    $business = Business::factory()->create(['trial_ends_at' => null]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id'      => 'phone_test_base',
            'whatsapp_ai_enabled'         => true,
            'whatsapp_notifications_enabled' => false,
        ]
    );

    $this->postJson('/webhook/whatsapp', whatsappPayload('phone_test_base', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});

it('dispatches AI job for trial business', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id' => 'phone_test_trial',
            'whatsapp_ai_enabled'    => true,
        ]
    );

    $this->postJson('/webhook/whatsapp', whatsappPayload('phone_test_trial', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertPushed(ProcessWhatsAppMessageJob::class);
});

it('does not dispatch AI job when ai_enabled is false even for plus plan', function () {
    $business = Business::factory()->create(['trial_ends_at' => now()->addDay()]);
    IntegrationSetting::withoutGlobalScopes()->updateOrCreate(
        ['business_id' => $business->id],
        [
            'meta_whatsapp_phone_id' => 'phone_test_disabled',
            'whatsapp_ai_enabled'    => false,
        ]
    );

    $this->postJson('/webhook/whatsapp', whatsappPayload('phone_test_disabled', '+39123456789', 'Ciao'))
         ->assertStatus(200);

    Queue::assertNotPushed(ProcessWhatsAppMessageJob::class);
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/WebhookPlanGatingTest.php
```

Expected: first test FAILS (job is dispatched even for base-plan business).

- [ ] **Step 3: Update WhatsAppWebhookController**

In `app/Http/Controllers/WhatsAppWebhookController.php`, add import at top:

```php
use App\Models\Business;
use Carbon\Carbon;
use App\Services\WhatsAppService;
```

Replace the `processMessage()` method body from line 153 onwards (the block that calls `$setting->hasWhatsAppAiEnabled()`):

```php
private function processMessage(array $messageData, array $value, IntegrationSetting $setting): void
{
    $wamid = $messageData['id'] ?? null;
    $waId  = $messageData['from'] ?? '';

    if (empty($waId)) {
        Log::warning('WhatsApp webhook received message with empty waId', [
            'type'            => $messageData['type'] ?? null,
            'phone_number_id' => data_get($value, 'metadata.phone_number_id'),
        ]);
        return;
    }

    $phone   = PhoneNormalizer::normalize('+' . ltrim($waId, '+'));
    $profile = collect(data_get($value, 'contacts', []))->firstWhere('wa_id', $waId);

    if ($wamid && WhatsAppMessage::findByWamid($wamid)) {
        return;
    }

    $message = WhatsAppMessage::create([
        'business_id'      => $setting->business_id,
        'wamid'            => $wamid,
        'idempotency_key'  => $wamid,
        'phone'            => '+' . ltrim($waId, '+'),
        'phone_normalized' => $phone,
        'wa_id'            => $waId,
        'profile_name'     => data_get($profile, 'profile.name'),
        'direction'        => 'inbound',
        'type'             => $messageData['type'] ?? 'text',
        'payload'          => $messageData,
    ]);

    $business = Business::find($setting->business_id);

    if (! $business?->canUseFeature('whatsapp_ai')) {
        if ($setting->whatsapp_notifications_enabled) {
            $rawPhone = '+' . ltrim($waId, '+');
            dispatch(function () use ($setting, $rawPhone) {
                app(WhatsAppService::class)->sendTextWithinWindow(
                    $rawPhone,
                    'Grazie per il messaggio. Il nostro team ti risponderà al più presto.',
                    Carbon::now(),
                    $setting->business_id,
                );
            });
        }
        return;
    }

    if (! $setting->hasWhatsAppAiEnabled()) {
        return;
    }

    ProcessWhatsAppMessageJob::dispatch($message->id, $setting->business_id);
}
```

- [ ] **Step 4: Update ProcessWhatsAppMessageJob**

In `app/Jobs/ProcessWhatsAppMessageJob.php`, add import:

```php
use App\Models\Business;
```

Replace the `handle()` method:

```php
public function handle(WhatsAppConversationService $service): void
{
    $message = WhatsAppMessage::find($this->messageId);

    if (! $message) {
        Log::warning('ProcessWhatsAppMessageJob: message not found', ['message_id' => $this->messageId]);
        return;
    }

    if ($message->direction !== 'inbound') {
        return;
    }

    if ($message->processed_at !== null) {
        return;
    }

    $business = Business::find($message->business_id);
    if (! $business?->canUseFeature('whatsapp_ai')) {
        Log::info('ProcessWhatsAppMessageJob: business not on plus plan, skipping', [
            'business_id' => $message->business_id,
        ]);
        return;
    }

    app()->instance('current_business_id', $message->business_id);
    $service->handle($this->messageId, $message->business_id);
}
```

- [ ] **Step 5: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/WebhookPlanGatingTest.php
```

Expected: 3 passed.

- [ ] **Step 6: Run full existing WhatsApp test suite to check for regressions**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/WhatsApp/
```

Expected: all existing tests pass.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/WhatsAppWebhookController.php \
        app/Jobs/ProcessWhatsAppMessageJob.php \
        tests/Feature/WhatsApp/WebhookPlanGatingTest.php
git commit -m "feat: gate WhatsApp AI on plus plan in webhook + job"
```

---

### Task 4: Stripe webhook listener

**Files:**
- Create: `app/Listeners/UpdateBusinessPlanFromStripe.php`
- Create: `tests/Feature/Listeners/UpdateBusinessPlanFromStripeTest.php`

**Interfaces:**
- Consumes: `Business::subscribedToPrice(string $priceId, string $type): bool` (Cashier)
- Consumes: `config('plans.plus.price_id')`
- Listens to: `Laravel\Cashier\Events\WebhookHandled`

- [ ] **Step 1: Write failing tests**

Create `tests/Feature/Listeners/UpdateBusinessPlanFromStripeTest.php`:

```php
<?php

use App\Listeners\UpdateBusinessPlanFromStripe;
use App\Models\Business;
use Laravel\Cashier\Events\WebhookHandled;

function makeWebhookHandledEvent(string $type, string $stripeCustomerId, ?string $stripePrice = null): WebhookHandled
{
    $object = ['customer' => $stripeCustomerId];
    if ($stripePrice) {
        $object['items'] = ['data' => [['price' => ['id' => $stripePrice]]]];
    }

    return new WebhookHandled([
        'type' => $type,
        'data' => ['object' => $object],
    ]);
}

it('updates plan to plus when subscription updated to plus price', function () {
    $plusPriceId = config('plans.plus.price_id') ?? 'price_plus_test';
    config(['plans.plus.price_id' => $plusPriceId]);

    $business = Business::factory()->create(['stripe_id' => 'cus_test_001', 'plan' => 'base']);

    // Mock subscribedToPrice to return true for plus price
    $business = Mockery::mock(Business::class)->makePartial();
    $business->id         = 1;
    $business->stripe_id  = 'cus_test_002';
    $business->shouldReceive('subscribedToPrice')
        ->with($plusPriceId, 'default')
        ->andReturn(true);
    $business->shouldReceive('update')
        ->with(['plan' => 'plus'])
        ->once();

    // Bind to query result
    Business::shouldReceive('where')
        ->with('stripe_id', 'cus_test_002')
        ->andReturnSelf();
    Business::shouldReceive('first')
        ->andReturn($business);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('customer.subscription.updated', 'cus_test_002', $plusPriceId));
});

it('resets plan to base when subscription is deleted', function () {
    $business = Business::factory()->create(['stripe_id' => 'cus_delete_001', 'plan' => 'plus']);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('customer.subscription.deleted', 'cus_delete_001'));

    expect($business->fresh()->plan)->toBe('base');
});

it('does nothing for unknown Stripe customer', function () {
    $listener = new UpdateBusinessPlanFromStripe();

    expect(fn () => $listener->handle(
        makeWebhookHandledEvent('customer.subscription.deleted', 'cus_unknown_999')
    ))->not->toThrow(\Throwable::class);
});

it('ignores unrelated webhook events', function () {
    $business = Business::factory()->create(['stripe_id' => 'cus_irrelevant', 'plan' => 'plus']);

    $listener = new UpdateBusinessPlanFromStripe();
    $listener->handle(makeWebhookHandledEvent('payment_intent.succeeded', 'cus_irrelevant'));

    expect($business->fresh()->plan)->toBe('plus'); // unchanged
});
```

- [ ] **Step 2: Run tests to verify they fail**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Listeners/UpdateBusinessPlanFromStripeTest.php
```

Expected: FAIL — class `UpdateBusinessPlanFromStripe` not found.

- [ ] **Step 3: Create listener**

Create `app/Listeners/UpdateBusinessPlanFromStripe.php`:

```php
<?php

namespace App\Listeners;

use App\Models\Business;
use Illuminate\Events\Attributes\ListensTo;
use Laravel\Cashier\Events\WebhookHandled;

#[ListensTo(WebhookHandled::class)]
class UpdateBusinessPlanFromStripe
{
    public function handle(WebhookHandled $event): void
    {
        $payload = $event->payload;
        $type    = $payload['type'] ?? null;

        if ($type === 'customer.subscription.updated') {
            $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
            $business = Business::where('stripe_id', $stripeCustomerId)->first();

            if (! $business) {
                return;
            }

            $plan = $business->subscribedToPrice(config('plans.plus.price_id'), 'default')
                ? 'plus'
                : 'base';

            $business->update(['plan' => $plan]);
        }

        if ($type === 'customer.subscription.deleted') {
            $stripeCustomerId = $payload['data']['object']['customer'] ?? null;
            $business = Business::where('stripe_id', $stripeCustomerId)->first();

            if (! $business) {
                return;
            }

            $business->update(['plan' => 'base']);
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Listeners/UpdateBusinessPlanFromStripeTest.php
```

Expected: 4 passed.

- [ ] **Step 5: Commit**

```bash
git add app/Listeners/UpdateBusinessPlanFromStripe.php \
        tests/Feature/Listeners/UpdateBusinessPlanFromStripeTest.php
git commit -m "feat: Stripe webhook listener keeps businesses.plan in sync"
```

---

### Task 5: BillingPage redesign

**Files:**
- Modify: `app/Filament/Pages/BillingPage.php` — add plan swap actions + private helpers
- Modify: `resources/views/filament/pages/billing.blade.php` — add plan cards section + trial notice

**Interfaces:**
- Consumes: `config('plans.base.*')`, `config('plans.plus.*')` from Task 1
- Consumes: `Business::effectivePlan()`, `Business::canUseFeature()` from Task 2

- [ ] **Step 1: Replace BillingPage PHP class**

Replace the entire contents of `app/Filament/Pages/BillingPage.php`:

```php
<?php

namespace App\Filament\Pages;

use App\Models\Business;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class BillingPage extends Page
{
    protected static ?string $navigationLabel = 'Abbonamento';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $slug = 'abbonamento';
    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.billing';

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function getBusiness(): Business
    {
        return once(fn () => Business::findOrFail(Business::currentId()));
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
        if (! Auth::user()?->isAdmin()) {
            return [];
        }

        $business = $this->getBusiness();
        $status   = $business->subscriptionStatus();

        return match (true) {
            in_array($status, ['trial', 'expired']) => [
                Action::make('subscribeBase')
                    ->label('Attiva Base — €' . config('plans.base.price') . '/mese')
                    ->color('gray')
                    ->icon('heroicon-o-credit-card')
                    ->action(fn () => $this->checkoutRedirect('base')),

                Action::make('subscribePlus')
                    ->label('Attiva Plus' . (config('plans.plus.price') ? ' — €' . config('plans.plus.price') . '/mese' : ''))
                    ->color('primary')
                    ->icon('heroicon-o-rocket-launch')
                    ->action(fn () => $this->checkoutRedirect('plus')),
            ],

            $status === 'active' && $business->plan === 'base' => [
                Action::make('upgradePlus')
                    ->label('Passa a Plus')
                    ->color('primary')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->action(fn () => $this->swapPlan('plus')),

                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription("L'abbonamento rimarrà attivo fino alla fine del periodo corrente.")
                    ->action(fn () => $this->cancelSubscription()),
            ],

            $status === 'active' && $business->plan === 'plus' => [
                Action::make('downgradeBase')
                    ->label('Torna a Base')
                    ->color('warning')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Torna al piano Base')
                    ->modalDescription('Il downgrade è immediato. WhatsApp AI verrà disattivato subito. Sei sicuro?')
                    ->action(fn () => $this->swapPlan('base')),

                Action::make('cancel')
                    ->label('Annulla abbonamento')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->modalHeading('Annulla abbonamento')
                    ->modalDescription("L'abbonamento rimarrà attivo fino alla fine del periodo corrente.")
                    ->action(fn () => $this->cancelSubscription()),
            ],

            $status === 'grace_period' => [
                Action::make('resume')
                    ->label('Riattiva abbonamento')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->action(fn () => $this->resumeSubscription()),
            ],

            default => [],
        };
    }

    private function checkoutRedirect(string $plan): void
    {
        $priceId = config("plans.{$plan}.price_id");

        if (! $priceId) {
            Notification::make()
                ->title("Prezzo per il piano {$plan} non ancora configurato.")
                ->danger()
                ->send();
            return;
        }

        $business = $this->getBusiness();
        $session  = $business->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=success',
                'cancel_url'  => route('filament.admin.pages.abbonamento', ['tenant' => $business->subdomain]) . '?checkout=cancelled',
            ]);

        $this->redirect($session->url, navigate: false);
    }

    private function swapPlan(string $plan): void
    {
        $priceId = config("plans.{$plan}.price_id");

        if (! $priceId) {
            Notification::make()
                ->title("Prezzo per il piano {$plan} non ancora configurato.")
                ->danger()
                ->send();
            return;
        }

        $business = $this->getBusiness();
        $business->subscription('default')->swapAndInvoice($priceId);

        $freshBusiness = $business->fresh();
        if ($freshBusiness->subscribed('default') && ! $freshBusiness->hasIncompletePayment('default')) {
            $business->update(['plan' => $plan]);
            Notification::make()
                ->title('Piano aggiornato con successo.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Pagamento in sospeso — il piano sarà aggiornato a breve.')
                ->warning()
                ->send();
        }
    }

    private function cancelSubscription(): void
    {
        $business     = $this->getBusiness();
        $subscription = $business->subscription('default');
        $subscription->cancel();
        $endsAt = $subscription->fresh()?->ends_at?->format('d/m/Y');
        Notification::make()
            ->title("Abbonamento annullato. Accesso garantito fino al {$endsAt}.")
            ->warning()
            ->send();
    }

    private function resumeSubscription(): void
    {
        $this->getBusiness()->subscription('default')->resume();
        Notification::make()
            ->title('Abbonamento riattivato!')
            ->success()
            ->send();
    }
}
```

- [ ] **Step 2: Update billing.blade.php — add trial plan notice + plan cards section**

In `resources/views/filament/pages/billing.blade.php`, modify the trial banner block (around line 50) to add plan info. Replace the trial banner `<div>` (lines 51–75):

```blade
        @elseif ($status === 'trial')
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/60 dark:border-amber-800 p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center shrink-0">
                            <x-heroicon-o-clock class="w-5 h-5 text-amber-600 dark:text-amber-400"/>
                        </div>
                        <div>
                            <p class="font-semibold text-amber-900 dark:text-amber-100">Periodo di prova attivo</p>
                            <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">
                                Termina il <strong>{{ $business->trial_ends_at->format('d/m/Y') }}</strong>
                                — {{ $daysLeft }} {{ $daysLeft === 1 ? 'giorno rimasto' : 'giorni rimasti' }}
                            </p>
                            <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">
                                Piano pagato: <strong>Base</strong> &nbsp;·&nbsp;
                                Accesso trial: <strong>Plus</strong> — alla fine del trial resterai su Base se non scegli Plus.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-xs text-amber-600 dark:text-amber-500">
                        <span>Inizio prova</span>
                        <span>{{ $daysLeft }} gg rimasti</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-amber-200 dark:bg-amber-800 overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500 dark:bg-amber-400 transition-all duration-500" style="width: {{ $trialProgress }}%"></div>
                    </div>
                </div>
            </div>
```

Then, after the status banner block (after the `@endif` at line 135) and before the `{{-- ═══════════ DETTAGLI ═══════════ --}}` comment, add the plan cards section:

```blade
        {{-- ═══════════ PIANI ═══════════ --}}
        @if ($isAdmin)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach (['base', 'plus'] as $planKey)
            @php
                $planConfig   = config("plans.{$planKey}");
                $isCurrentPaidPlan = $business->plan === $planKey && $business->subscribed('default');
                $isEffectivePlan   = $business->effectivePlan() === $planKey;
                $isPlusPlan        = $planKey === 'plus';
            @endphp
            <div class="rounded-xl border {{ $isPlusPlan ? 'border-primary-500 dark:border-primary-400' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-6 flex flex-col relative">
                @if ($isCurrentPaidPlan)
                    <span class="absolute top-4 right-4 inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:text-primary-200">Piano attuale</span>
                @endif

                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white">{{ $planConfig['label'] }}</h3>
                    <p class="text-2xl font-bold text-gray-900 dark:text-white mt-1">
                        @if ($planConfig['price'])
                            €{{ $planConfig['price'] }}<span class="text-sm font-normal text-gray-500 dark:text-gray-400">/mese</span>
                        @else
                            <span class="text-base font-normal text-gray-500 dark:text-gray-400">Prezzo da definire</span>
                        @endif
                    </p>
                </div>

                <ul class="space-y-2 flex-1 mb-6">
                    @foreach ($planConfig['features'] as $feature)
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <x-heroicon-m-check-circle class="w-4 h-4 text-teal-500 shrink-0"/>
                        {{ $feature }}
                    </li>
                    @endforeach
                </ul>

                @if (in_array($status, ['trial', 'expired']))
                    <button wire:click="mountAction('subscribe{{ ucfirst($planKey) }}')"
                            class="w-full rounded-lg px-4 py-2 text-sm font-semibold {{ $isPlusPlan ? 'bg-primary-600 hover:bg-primary-700 text-white' : 'bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white' }} transition-colors">
                        Attiva {{ $planConfig['label'] }}
                    </button>
                @elseif ($status === 'active' && !$isCurrentPaidPlan)
                    @if ($planKey === 'plus')
                        <button wire:click="mountAction('upgradePlus')"
                                class="w-full rounded-lg px-4 py-2 text-sm font-semibold bg-primary-600 hover:bg-primary-700 text-white transition-colors">
                            Passa a Plus
                        </button>
                    @else
                        <button wire:click="mountAction('downgradeBase')"
                                class="w-full rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white transition-colors">
                            Torna a Base
                        </button>
                    @endif
                @endif
            </div>
            @endforeach
        </div>
        @endif
```

Also update the active subscription plan name in the status banner (line 84 — replace the hardcoded `"Piano attivo — BookingApp"`):

```blade
                        <p class="font-semibold text-green-900 dark:text-green-100">Piano attivo — {{ ucfirst($business->plan) }}</p>
                        <p class="text-sm text-green-700 dark:text-green-400 mt-0.5">€{{ config("plans.{$business->plan}.price") }}/mese · IVA esclusa · Cancellazione in qualsiasi momento</p>
```

And the payment detail `€29,00` (line 208 — replace hardcoded price):

```blade
                        <span class="font-medium text-gray-900 dark:text-white">€{{ number_format(config("plans.{$business->plan}.price", 29), 2, ',', '.') }}</span>
```

- [ ] **Step 3: Verify in browser**

```bash
docker-compose up -d
```

Navigate to `/admin/{your-subdomain}/abbonamento` as admin. Verify:
- Trial state: amber banner with trial days + plan notice; two plan cards with "Attiva Base" / "Attiva Plus"
- Active Base: green banner with "Piano attivo — Base"; Plus card shows "Passa a Plus" button
- Active Plus: green banner with "Piano attivo — Plus"; Base card shows "Torna a Base" button

- [ ] **Step 4: Commit**

```bash
git add app/Filament/Pages/BillingPage.php \
        resources/views/filament/pages/billing.blade.php
git commit -m "feat: redesign BillingPage with two plan cards and swap actions"
```

---

### Task 6: IntegrationSettings — gate AI section

**Files:**
- Modify: `app/Filament/Pages/IntegrationSettings.php` — disable AI section for base plan, add upgrade CTA

**Interfaces:**
- Consumes: `Business::canUseFeature('whatsapp_ai')` from Task 2

- [ ] **Step 1: Add getBusiness() method and plan gate to IntegrationSettings**

In `app/Filament/Pages/IntegrationSettings.php`, add import at top:

```php
use App\Models\Business;
use Filament\Schemas\Components\Text;
```

Add a new method to the class (after `mount()`):

```php
public function getBusiness(): Business
{
    return once(fn () => Business::findOrFail(Business::currentId()));
}
```

In the `form()` method, find the `Section::make('Assistente WhatsApp (AI)')` block (starting around line 74). Replace it with a version that conditionally disables the fields:

```php
Section::make('Assistente WhatsApp (AI)')
    ->description('Abilita un assistente conversazionale AI per ricevere prenotazioni via WhatsApp. Richiede le credenziali Meta WhatsApp configurate sopra.')
    ->schema(function (): array {
        $hasPlan = $this->getBusiness()->canUseFeature('whatsapp_ai');

        $upgradeNotice = $hasPlan ? [] : [
            \Filament\Schemas\Components\Text::make('upgrade_notice')
                ->default('')
                ->extraAttributes(['class' => 'hidden'])
                ->hint('Disponibile nel piano Plus.')
                ->hintIcon('heroicon-o-rocket-launch')
                ->hintColor('primary'),
        ];

        return [
            ...$upgradeNotice,

            Toggle::make('whatsapp_ai_enabled')
                ->label('Assistente WhatsApp attivo')
                ->helperText('Attiva il bot AI per rispondere ai messaggi in arrivo su WhatsApp.')
                ->disabled(! $hasPlan),

            Toggle::make('whatsapp_ai_booking_enabled')
                ->label('Permetti prenotazione via WhatsApp')
                ->default(true)
                ->disabled(! $hasPlan),

            Toggle::make('whatsapp_ai_cancellation_enabled')
                ->label('Permetti cancellazione via WhatsApp')
                ->helperText('Se disabilitato, il bot non potrà cancellare appuntamenti.')
                ->default(false)
                ->disabled(! $hasPlan),

            TextInput::make('whatsapp_ai_handoff_email')
                ->label('Email notifica escalation staff')
                ->helperText('Indirizzo a cui inviare la notifica quando il bot trasferisce a un operatore umano.')
                ->email()
                ->nullable()
                ->disabled(! $hasPlan),

            TextInput::make('whatsapp_ai_max_turns')
                ->label('Numero max turni')
                ->helperText('Limite di messaggi per conversazione prima di invitare il cliente a contattare direttamente il salone. Default: 12.')
                ->numeric()
                ->default(12)
                ->minValue(4)
                ->maxValue(50)
                ->disabled(! $hasPlan),

            Textarea::make('whatsapp_ai_custom_instructions')
                ->label('Istruzioni personalizzate')
                ->helperText('Personalizza tono e identità dell\'assistente.')
                ->rows(4)
                ->nullable()
                ->disabled(! $hasPlan),

            Placeholder::make('webhook_url')
                ->label('URL webhook da registrare su Meta Developer Console')
                ->content(fn () => url('/whatsapp/webhook'))
                ->helperText('Subscribed fields: messages'),
        ];
    }),
```

Note: `Filament\Schemas\Components\Text` in Filament 4 might not support `->hint()` directly on a text component; use `\Filament\Forms\Components\Placeholder` with `->hint()` instead if `Text` doesn't work. Verify in browser.

- [ ] **Step 2: Verify in browser**

Navigate to Integrazioni page as admin of a base-plan business. Verify:
- AI section toggles are disabled (greyed out)
- Upgrade notice shows with Plus hint

Navigate as a trial/plus-plan business. Verify:
- AI section is fully editable

- [ ] **Step 3: Commit**

```bash
git add app/Filament/Pages/IntegrationSettings.php
git commit -m "feat: disable WhatsApp AI settings for base-plan businesses"
```

---

### Task 7: SuperAdmin — plan display + override action

**Files:**
- Modify: `app/Filament/SuperAdmin/Resources/BusinessResource.php` — add plan columns + override action

**Interfaces:**
- Consumes: `Business::effectivePlan()` from Task 2
- Consumes: `Business::plan`, `Business::plan_override`, `Business::plan_override_expires_at`, `Business::plan_override_reason` columns from Task 1

- [ ] **Step 1: Add plan columns to the table**

In `app/Filament/SuperAdmin/Resources/BusinessResource.php`, add to the `->modifyQueryUsing(...)` eager loads:

```php
->modifyQueryUsing(fn($query) => $query->with(['subscriptions', 'admins']))
```

This stays the same (no new eager load needed).

After the `subscriptionStatus` `TextColumn` (around line 153), add two new columns:

```php
TextColumn::make('plan')
    ->label('Piano pagato')
    ->badge()
    ->color(fn (string $state): string => match ($state) {
        'plus'  => 'primary',
        default => 'gray',
    })
    ->formatStateUsing(fn (string $state): string => ucfirst($state))
    ->toggleable(),

TextColumn::make('effectivePlan')
    ->label('Accesso effettivo')
    ->badge()
    ->state(fn (Business $record): string => $record->effectivePlan())
    ->color(fn (string $state): string => match ($state) {
        'plus'  => 'success',
        default => 'gray',
    })
    ->formatStateUsing(fn (string $state): string => ucfirst($state) . ($state !== (string) $record->plan ? ' (override/trial)' : ''))
    ->toggleable(),
```

Note: the `formatStateUsing` closure for `effectivePlan` references `$record` — use the approach below to fix the scoping:

```php
TextColumn::make('effectivePlan')
    ->label('Accesso effettivo')
    ->badge()
    ->state(fn (Business $record): string => $record->effectivePlan())
    ->color(fn (string $state): string => $state === 'plus' ? 'success' : 'gray')
    ->formatStateUsing(fn (string $state, Business $record): string =>
        ucfirst($state) . ($state !== $record->plan ? ' ★' : '')
    )
    ->toggleable(),
```

- [ ] **Step 2: Add plan override action**

In the `->actions([...])` array in `table()`, add these two actions after the existing `cancelSubscriptionNow` action:

```php
Action::make('grantPlusOverride')
    ->label('Concedi Plus')
    ->icon('heroicon-o-rocket-launch')
    ->color('primary')
    ->visible(fn (Business $record): bool => $record->plan_override !== 'plus' || $record->hasActivePlanOverride() === false)
    ->form([
        \Filament\Forms\Components\DateTimePicker::make('plan_override_expires_at')
            ->label('Scade il (lascia vuoto per indefinito)')
            ->nullable(),

        \Filament\Forms\Components\TextInput::make('plan_override_reason')
            ->label('Motivo (obbligatorio)')
            ->required()
            ->placeholder('es. test interno, supporto cliente'),
    ])
    ->action(function (Business $record, array $data): void {
        $record->update([
            'plan_override'            => 'plus',
            'plan_override_expires_at' => $data['plan_override_expires_at'] ?? null,
            'plan_override_reason'     => $data['plan_override_reason'],
        ]);

        Notification::make()
            ->title('Accesso Plus concesso.')
            ->success()
            ->send();
    }),

Action::make('revokeOverride')
    ->label('Revoca override')
    ->icon('heroicon-o-x-mark')
    ->color('warning')
    ->visible(fn (Business $record): bool => $record->plan_override !== null)
    ->requiresConfirmation()
    ->modalDescription('L\'accesso sarà determinato dal piano Stripe effettivo.')
    ->action(function (Business $record): void {
        $record->update([
            'plan_override'            => null,
            'plan_override_expires_at' => null,
            'plan_override_reason'     => null,
        ]);

        Notification::make()
            ->title('Override revocato.')
            ->success()
            ->send();
    }),
```

- [ ] **Step 3: Verify in browser**

Navigate to `/superadmin/saloni`. Verify:
- "Piano pagato" column shows `Base` / `Plus` badges
- "Accesso effettivo" shows `Plus ★` for trial businesses (override of trial)
- "Concedi Plus" action shows a form with expiry + reason
- "Revoca override" shows only when override is active
- Granting and revoking override reflects immediately in "Accesso effettivo" column

- [ ] **Step 4: Run the full test suite**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/SuperAdmin/Resources/BusinessResource.php
git commit -m "feat: add plan display and override actions to SuperAdmin BusinessResource"
```

---

## Self-Review

**Spec coverage check:**

| Spec requirement | Task |
|---|---|
| `plan` + `plan_override` columns | Task 1 |
| `config/plans.php` with features map | Task 1 |
| `effectivePlan()` + `hasActivePlanOverride()` + `canUseFeature()` | Task 2 |
| `PlanFeatureGate` service | Task 2 |
| Webhook gate + fallback reply | Task 3 |
| Job defensive guard | Task 3 |
| Stripe webhook listener (WebhookHandled) | Task 4 |
| BillingPage two-plan cards | Task 5 |
| Trial banner plan notice | Task 5 |
| `swapAndInvoice()` + conditional column update | Task 5 |
| IntegrationSettings AI section disabled for base | Task 6 |
| SuperAdmin plan badges | Task 7 |
| SuperAdmin plan_override action | Task 7 |

All spec sections covered.

**Placeholder scan:** None. All steps contain complete, runnable code.

**Type consistency:**
- `effectivePlan()` returns `string` (`'base'` or `'plus'`) — used consistently in Tasks 3, 4, 5, 6, 7
- `canUseFeature(string $feature)` returns `bool` — used in Tasks 3, 6
- `config('plans.plus.price_id')` — used in Tasks 2, 4, 5; same key throughout
- `config('plans.base.features')` / `config('plans.plus.features')` — used in Task 5 view

**One gap to note:** Task 6 uses `\Filament\Schemas\Components\Text` for the upgrade notice — if this component doesn't exist in Filament 4, replace with `\Filament\Forms\Components\Placeholder` with a custom hint message. The developer should verify the available Filament 4 schema components.

**STRIPE_PRICE_ID_PLUS must be set before the Plus plan subscribe/upgrade CTA will work.** The `checkoutRedirect()` and `swapPlan()` methods guard against a null price ID with a notification — no crash.
