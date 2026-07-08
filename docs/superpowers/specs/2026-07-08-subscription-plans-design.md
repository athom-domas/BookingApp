# Subscription Plans: Base & Plus

**Date:** 2026-07-08
**Status:** Approved (rev 2 — incorporating peer review)
**Branch:** feature/whatsapp-ai-booking

---

## Problem

The app has a single flat subscription. The WhatsApp AI assistant is a premium feature that should only be available to businesses on a higher-tier plan. Adding a second plan (Plus) requires gating the AI feature and giving customers a self-service upgrade path.

---

## Goals

- Define two plans: **Base** (current) and **Plus** (includes WhatsApp AI)
- Customers can upgrade/downgrade self-service from the Billing page
- Trial businesses get Plus access to demonstrate full product value
- Existing businesses default to Base with no disruption
- Feature gates must reflect real Stripe subscription state, not just a DB column

---

## Out of Scope

- Usage-based billing, metered features
- Per-seat pricing
- Annual billing toggle
- Scheduled downgrade (deferred to post-MVP; downgrade is immediate for MVP)

---

## Architecture

### 1. Data Model

**Migration**: two changes to `businesses`:

```php
$table->enum('plan', ['base', 'plus'])->default('base');

// Superadmin manual override — separate from the paid plan
$table->enum('plan_override', ['base', 'plus'])->nullable();
$table->timestamp('plan_override_expires_at')->nullable();
$table->string('plan_override_reason')->nullable();
```

Existing businesses get `plan = 'base'` via default. No backfill needed. `plan_override` starts null for everyone.

---

**`config/plans.php`** — single source of truth for plan definitions and feature mapping:

```php
return [
    'base' => [
        'price_id' => env('STRIPE_PRICE_ID_BASE'),
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
        'price'    => env('PLAN_PLUS_PRICE', null), // confirm price before going live
        'features' => [
            'Tutto il piano Base',
            'Assistente AI WhatsApp',
            'Prenotazioni via WhatsApp',
            'Cancellazioni via WhatsApp',
        ],
    ],

    // Feature → required plans mapping.
    // A feature is allowed if the business's effective plan is in this list.
    'features' => [
        'whatsapp_ai'           => ['plus'],
        'whatsapp_booking'      => ['plus'],
        'whatsapp_cancellation' => ['plus'],
    ],
];
```

**.env** additions (price IDs are application config, not Cashier config — kept only in `config/plans.php`):
```
STRIPE_PRICE_ID_BASE=price_...   # rename from existing STRIPE_PRICE_ID
STRIPE_PRICE_ID_PLUS=price_...
PLAN_BASE_PRICE=29
PLAN_PLUS_PRICE=             # confirm before launch
```

---

### 2. Business Model — Plan Resolution

`businesses.plan` is an applicative cache/denormalization. Stripe is the economic source of truth. `effectivePlan()` always verifies against the live subscription state via Cashier.

```php
public function effectivePlan(): string
{
    // Superadmin manual override (e.g. for internal testing/support)
    if ($this->hasActivePlanOverride()) {
        return $this->plan_override;
    }

    // Trial gets full Plus access (conversion strategy)
    if ($this->onGenericTrial()) {
        return 'plus';
    }

    // No active subscription → Base
    if (! $this->subscribed('default')) {
        return 'base';
    }

    // Subscription exists but payment is incomplete/requires SCA → treat as Base
    if ($this->hasIncompletePayment('default')) {
        return 'base';
    }

    // Verify against actual Stripe price — do not trust column alone
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
    return app(\App\Services\PlanFeatureGate::class)->allows($this, $feature);
}
```

`canUseFeature()` is the **only public API** for feature gating — callers never inspect plan names directly. This means adding a new tier or add-on in the future requires changing only `PlanFeatureGate` and the config, not 20 controllers.

---

### 3. PlanFeatureGate Service

New class `app/Services/PlanFeatureGate.php`:

```php
public function allows(Business $business, string $feature): bool
{
    $requiredPlans = config("plans.features.{$feature}");

    if ($requiredPlans === null) {
        // Unknown feature → deny by default (fail-safe)
        return false;
    }

    return in_array($business->effectivePlan(), $requiredPlans, strict: true);
}
```

Resolves from `config/plans.php` features map. Registered in the service container (singleton).

---

### 4. Stripe Sync — Keeping `plan` Column Up to Date

`businesses.plan` is updated in two places:

**A. BillingPage (happy path)**

```php
$business->subscription('default')->swapAndInvoice($targetPriceId);

// Only update column if swap succeeded (no incomplete payment / SCA pending)
if ($business->subscribed('default') && ! $business->hasIncompletePayment('default')) {
    $business->update(['plan' => $targetPlanKey]);
}
```

Using `swapAndInvoice()` rather than `swap()` — invoices immediately so the upgrade is active at once, not at next billing cycle.

**B. Cashier webhook listener (resilience)**

New listener `UpdateBusinessPlanFromStripe` using `#[ListensTo(\Laravel\Cashier\Events\WebhookHandled::class)]`.

Using `WebhookHandled` (not `WebhookReceived`) so that Cashier has already updated the `subscriptions` table before the listener fires — the listener can safely read `subscribedToPrice()` from the DB.

Handled events:
- `customer.subscription.updated` — re-resolves plan from `subscribedToPrice()` and updates column
- `customer.subscription.deleted` — resets column to `base`

**What is NOT handled via a plan column change** (handled implicitly by `effectivePlan()` reading live state):
- `invoice.payment_failed` → subscription enters `past_due`/`incomplete` → `hasIncompletePayment()` returns true → `effectivePlan()` returns `base` automatically, no column update needed

---

### 5. BillingPage Redesign

Two-column plan comparison layout replacing the current single-action page.

**Each plan card shows**: name, price/month, feature checklist, CTA button.

**CTA logic:**
- Current paid plan → badge "Piano attuale" (no button)
- On trial → banner: *"Stai usando il trial Plus. Alla fine del trial resterai sul piano Base se non scegli Plus."* Both plan cards visible; current effective plan labeled "Accesso trial"
- Upgrade to Plus → "Passa a Plus" — `swapAndInvoice()`, immediate, Stripe proration applies
- Downgrade to Base → "Torna a Base" — confirmation modal that states explicitly: *"Il downgrade è immediato. WhatsApp AI verrà disattivato subito."* Then `swapAndInvoice()`.

**Downgrade policy (explicit for MVP):** Immediate. The customer loses Plus features the moment the swap completes. Commercial mitigation (downgrade at end of cycle) is deferred post-MVP.

**Trial UI clarity:** When `onGenericTrial()` is true, the page shows:
```
Piano sottoscritto: Base
Accesso attuale: Plus (trial fino al gg/mm/yyyy)
```
This prevents the customer from thinking they are paying for Plus.

---

### 6. Feature Gating

Feature check via `canUseFeature()` — must be applied at **every entry point**:

| Location | Gate |
|---|---|
| `WhatsAppWebhookController` | `$business->canUseFeature('whatsapp_ai')` — load `$business` via `Business::find($setting->business_id)` |
| `ProcessWhatsAppMessageJob` | Same check as defensive guard |
| `WhatsAppConversationService::handle()` | Same check |
| `IntegrationSettings` Filament page | AI toggle: disabled + "Disponibile nel piano Plus" CTA if `!canUseFeature('whatsapp_ai')` |

**Base plan WhatsApp behavior:** When `canUseFeature('whatsapp_ai')` is false, the webhook does NOT silently drop the message. Instead it sends a simple fallback reply:

> *"Grazie per il messaggio. Il nostro team ti risponderà al più presto."*

This prevents the customer from experiencing a "broken" WhatsApp number. The fallback is sent only if `whatsapp_notifications_enabled` is active for that business (no fallback if the business hasn't set up WhatsApp at all).

---

### 7. SuperAdmin — BusinessResource

**Table columns:**
- "Piano pagato" badge → `$record->plan` (column value — what they subscribed to): `base` = gray, `plus` = purple
- "Accesso effettivo" badge → `$record->effectivePlan()`: useful to spot trial/override differences

**Actions:**

Replace direct "Cambia piano" with a safe override action:

*"Concedi accesso Plus"* → modal with fields:
- `plan_override`: `plus` (fixed)
- `plan_override_expires_at`: date picker (optional — null = indefinite)
- `plan_override_reason`: text (required — e.g. "test interno", "supporto cliente")

This writes to the three `plan_override_*` columns, leaving `plan` (the paid plan) untouched. A subsequent Stripe webhook will never overwrite an override because `effectivePlan()` checks override first.

*"Revoca override"* → sets `plan_override = null`, `plan_override_expires_at = null`, `plan_override_reason = null`.

---

## Data Flow Summary

```
Customer clicks "Passa a Plus"
    → BillingPage swapAndInvoice(STRIPE_PRICE_ID_PLUS)
    → If no incomplete payment → businesses.plan = 'plus'
    → effectivePlan() = subscribedToPrice(plus) → 'plus'
    → canUseFeature('whatsapp_ai') = true

Payment fails mid-cycle
    → Stripe subscription enters past_due / incomplete
    → No webhook column change needed
    → effectivePlan(): hasIncompletePayment() = true → returns 'base'
    → canUseFeature('whatsapp_ai') = false → AI denied automatically

Customer cancels subscription
    → Stripe fires customer.subscription.deleted
    → WebhookHandled listener fires → businesses.plan = 'base'
    → effectivePlan(): !subscribed() → 'base'
    → AI denied

Base-plan customer sends WhatsApp message
    → Webhook controller: canUseFeature('whatsapp_ai') = false
    → AI NOT invoked; fallback reply sent if whatsapp_notifications_enabled
```

---

## Testing

**Unit — `Business::effectivePlan()`:**

1. Generic trial → `plus`
2. Active Base subscription (price = Base ID) → `base`
3. Active Plus subscription (price = Plus ID) → `plus`
4. `plan` column = `plus` but `subscribedToPrice(plus)` = false → `base` (column ignored)
5. Subscription `past_due`/`incomplete` → `hasIncompletePayment()` = true → `base`
6. No subscription → `base`
7. Active `plan_override = 'plus'` (non-expired) → `plus` regardless of subscription
8. Expired `plan_override` → falls through to subscription check

**Feature — BillingPage:**

9. swapAndInvoice to Plus → `plan` column updated only if no incomplete payment
10. swapAndInvoice to Base → `plan` column reset to `base`; confirmation modal shown

**Feature — Webhook listener:**

11. `customer.subscription.updated` with Plus price → `plan = 'plus'`
12. `customer.subscription.updated` with Base price → `plan = 'base'`
13. `customer.subscription.deleted` → `plan = 'base'`
14. `invoice.payment_failed` → no column change; gate blocks via `hasIncompletePayment()`

**Feature — PlanFeatureGate:**

15. Base business → `canUseFeature('whatsapp_ai')` = false
16. Plus business → `canUseFeature('whatsapp_ai')` = true
17. Unknown feature string → returns false (fail-safe)
18. `Plan::fromStripePriceId($unknownId)` → throws / returns null with clear error (no silent fallback to wrong plan)

**Feature — IntegrationSettings:**

19. Base plan → AI toggle disabled, upgrade CTA shown
20. Plus plan → AI toggle enabled and functional

**Feature — WhatsApp fallback:**

21. Base-plan business with `whatsapp_notifications_enabled = true` → fallback message sent, AI not called
22. Base-plan business with `whatsapp_notifications_enabled = false` → no fallback (nothing sent)

---

## Files Changed

| File | Change |
|---|---|
| `database/migrations/xxxx_add_plan_to_businesses.php` | `plan`, `plan_override`, `plan_override_expires_at`, `plan_override_reason` |
| `config/plans.php` | New — plans, prices, features map |
| `app/Models/Business.php` | `effectivePlan()`, `hasActivePlanOverride()`, `canUseFeature()` |
| `app/Services/PlanFeatureGate.php` | New — feature gate service |
| `app/Listeners/UpdateBusinessPlanFromStripe.php` | New — `#[ListensTo(WebhookHandled)]` listener |
| `app/Http/Controllers/WhatsAppWebhookController.php` | `canUseFeature('whatsapp_ai')` gate + fallback reply |
| `app/Jobs/ProcessWhatsAppMessageJob.php` | Defensive `canUseFeature` guard |
| `app/Services/WhatsAppConversationService.php` | Defensive `canUseFeature` guard |
| `app/Filament/Pages/BillingPage.php` | Two-plan UI, swap actions, trial banner |
| `app/Filament/Pages/IntegrationSettings.php` | Gated AI toggle with upgrade CTA |
| `app/Filament/SuperAdmin/Resources/BusinessResource.php` | Plan badges, override action |
| `tests/Unit/Models/BusinessPlanTest.php` | Unit tests (cases 1–8) |
| `tests/Feature/BillingPlanTest.php` | Feature tests (cases 9–22) |
