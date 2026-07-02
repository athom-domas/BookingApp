# Stripe Connect: Direct Charges — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Migrare i pagamenti appuntamenti da destination charges a direct charges — PaymentIntent creato sul connected account con `application_fee_amount`, senza `transfer_data`/`on_behalf_of`.

**Architecture:** `PaymentService` crea PaymentIntent sul connected account via request option `stripe_account`. Gli eventi payment arrivano al Connect webhook e vengono instradati a `PaymentService`/`RefundService` che usano `withoutGlobalScopes()` + filtro `stripe_account_id`. Il frontend inizializza Stripe.js con `stripeAccount`.

**Tech Stack:** Laravel 13, PHP 8.4, Stripe PHP SDK ^17.3, Pest

## Global Constraints

- Test sempre con: `docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest <file>`
- Non modificare `ProductOrderService` né `StripeWebhookController` (fuori scope)
- Il fallback `hasConnect = false` in `PaymentService` rimane invariato (pagamenti senza Connect non sono impattati)
- Google Pay rimane disabilitato (`googlePay: 'never'`)

---

### Task 1: PaymentService — direct charges in `initiateStripePayment`

**Files:**
- Modify: `app/Services/PaymentService.php:45-56`
- Modify: `tests/Feature/Services/PaymentServiceTest.php:225-303`

**Interfaces:**
- Produce: `PaymentService::initiateStripePayment()` crea PI sul connected account via `['stripe_account' => ...]`, senza `on_behalf_of`/`transfer_data`

- [ ] **Step 1: Aggiornare il test esistente che verifica destination charge**

Aprire `tests/Feature/Services/PaymentServiceTest.php`. Il test `initiateStripePayment aggiunge destination charge params` (riga ~225) verifica `on_behalf_of` e `transfer_data`. Sostituirlo con:

```php
it('initiateStripePayment usa direct charge con stripe_account option se business ha account attivo', function () {
    $account = \App\Models\StripeConnectAccount::factory()->create([
        'business_id'       => $this->business->id,
        'stripe_account_id' => 'acct_direct_test',
    ]);

    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $this->business->update(['stripe_platform_fee_percent' => 5.0]);

    $fakeIntent = \Stripe\PaymentIntent::constructFrom([
        'id'            => 'pi_direct_test',
        'object'        => 'payment_intent',
        'amount'        => 10000,
        'currency'      => 'eur',
        'status'        => 'requires_payment_method',
        'client_secret' => 'pi_direct_test_secret',
    ]);

    $capturedParams = null;
    $capturedOpts   = null;

    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('create')
        ->once()
        ->withArgs(function ($params, $opts = []) use (&$capturedParams, &$capturedOpts) {
            $capturedParams = $params;
            $capturedOpts   = $opts;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    $service = new \App\Services\PaymentService(
        $mockStripe,
        app(\App\Services\StripeConnectService::class)
    );
    $payment = $service->initiateStripePayment($appointment->id, 10000, $this->business);

    expect(array_key_exists('on_behalf_of', $capturedParams))->toBeFalse();
    expect(array_key_exists('transfer_data', $capturedParams))->toBeFalse();
    expect($capturedParams['application_fee_amount'])->toBe(500);
    expect($capturedOpts['stripe_account'])->toBe('acct_direct_test');
    expect($payment->stripe_account_id)->toBe('acct_direct_test');
    expect($payment->platform_fee_amount)->toBe(500);
});
```

- [ ] **Step 2: Eseguire il test per verificare che fallisce**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "usa direct charge"
```

Atteso: FAIL (il codice ancora usa destination charge).

- [ ] **Step 3: Aggiornare `initiateStripePayment` in `PaymentService`**

In `app/Services/PaymentService.php`, sostituire il blocco `if ($hasConnect)`:

```php
if ($hasConnect) {
    $intentParams['application_fee_amount'] = $fee['cents'];
    $connectAccountId = $connectAccount->stripe_account_id;
} elseif ($pmConfig) {
```

E cambiare la chiamata `create`:

```php
if ($hasConnect) {
    $paymentIntent = $this->stripe->paymentIntents->create(
        $intentParams,
        ['stripe_account' => $connectAccountId]
    );
} else {
    $paymentIntent = $this->stripe->paymentIntents->create($intentParams);
}
```

Il blocco completo `$intentParams` diventa:

```php
$intentParams = [
    'amount'   => $amountCents,
    'currency' => 'eur',
    'metadata' => [
        'appointment_id' => $appointmentId,
        'business_id'    => $business?->id,
    ],
];

if ($hasConnect) {
    $intentParams['application_fee_amount']    = $fee['cents'];
    $intentParams['automatic_payment_methods'] = ['enabled' => true];
    $paymentIntent = $this->stripe->paymentIntents->create(
        $intentParams,
        ['stripe_account' => $connectAccount->stripe_account_id]
    );
} elseif ($pmConfig) {
    $intentParams['payment_method_configuration'] = $pmConfig;
    $paymentIntent = $this->stripe->paymentIntents->create($intentParams);
} else {
    $intentParams['automatic_payment_methods'] = ['enabled' => true];
    $paymentIntent = $this->stripe->paymentIntents->create($intentParams);
}
```

- [ ] **Step 4: Eseguire il test per verificare che passa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "usa direct charge"
```

Atteso: PASS.

- [ ] **Step 5: Eseguire tutta la suite PaymentService**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Atteso: tutti PASS (il test "non aggiunge destination params" deve passare invariato).

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(payments): migrate to direct charges in initiateStripePayment"
```

---

### Task 2: PaymentService — `confirmPayment` con `stripe_account` e salvataggio `latest_charge`

**Files:**
- Modify: `app/Services/PaymentService.php:113-131`
- Modify: `tests/Feature/Services/PaymentServiceTest.php:110-135`

**Interfaces:**
- Consume: `Payment.stripe_account_id` (nullable string)
- Produce: `confirmPayment()` usa `stripe_account` option; salva `stripe_charge_id` da `latest_charge` se non già presente

- [ ] **Step 1: Aggiungere test per confirmPayment con direct charge**

In `tests/Feature/Services/PaymentServiceTest.php`, aggiungere dopo i test esistenti di `confirmPayment`:

```php
it('confirmPayment passa stripe_account option e salva latest_charge', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'stripe_transaction_id' => 'pi_confirm_direct',
        'stripe_account_id' => 'acct_confirm',
        'status'            => 'pending',
    ]);

    $fakeIntent = \Stripe\PaymentIntent::constructFrom([
        'id'             => 'pi_confirm_direct',
        'object'         => 'payment_intent',
        'status'         => 'succeeded',
        'latest_charge'  => 'ch_direct_001',
        'amount'         => 5000,
        'currency'       => 'eur',
    ]);

    $capturedOpts = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('retrieve')
        ->once()
        ->withArgs(function ($id, $params, $opts) use (&$capturedOpts) {
            $capturedOpts = $opts;
            return true;
        })
        ->andReturn($fakeIntent);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    ($this->makePaymentService)($mockStripe)->confirmPayment($appointment->id);

    expect($capturedOpts['stripe_account'])->toBe('acct_confirm');
    expect($payment->fresh()->stripe_charge_id)->toBe('ch_direct_001');
});
```

- [ ] **Step 2: Eseguire il test per verificare che fallisce**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "confirmPayment passa stripe_account"
```

Atteso: FAIL.

- [ ] **Step 3: Aggiornare `confirmPayment` in `PaymentService`**

Sostituire il metodo `confirmPayment`:

```php
public function confirmPayment(int $appointmentId): Payment
{
    $appointment = Appointment::findOrFail($appointmentId);
    $payment = $appointment->payment;

    if (! $payment) {
        throw new BookingException('Nessun pagamento trovato per questo appuntamento.');
    }

    $opts = $payment->stripe_account_id
        ? ['stripe_account' => $payment->stripe_account_id]
        : [];

    $paymentIntent = $this->stripe->paymentIntents->retrieve(
        $payment->stripe_transaction_id,
        [],
        $opts
    );

    if ($paymentIntent->status === 'succeeded') {
        $chargeId = $paymentIntent->latest_charge ?? null;
        if ($chargeId && ! $payment->stripe_charge_id) {
            $payment->update(['stripe_charge_id' => $chargeId]);
        }
        $this->markPaymentCompleted($payment);
    } elseif (in_array($paymentIntent->status, ['canceled', 'requires_payment_method'], true)) {
        throw new BookingException('Il pagamento non è andato a buon fine.');
    }

    return $payment->fresh();
}
```

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Atteso: tutti PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(payments): add stripe_account option to confirmPayment, save latest_charge"
```

---

### Task 3: PaymentService — `cancelPendingPayment` con `stripe_account`

**Files:**
- Modify: `app/Services/PaymentService.php:133-149`

**Interfaces:**
- Produce: `cancelPendingPayment()` passa `stripe_account` option a `paymentIntents->cancel()`

- [ ] **Step 1: Aggiungere test**

```php
it('cancelPendingPayment passa stripe_account option per direct charge', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'       => $appointment->id,
        'stripe_transaction_id' => 'pi_cancel_direct',
        'stripe_account_id'    => 'acct_cancel',
        'status'               => 'pending',
        'payment_method'       => 'stripe',
    ]);

    $capturedOpts = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('cancel')
        ->once()
        ->withArgs(function ($id, $params, $opts) use (&$capturedOpts) {
            $capturedOpts = $opts;
            return true;
        });

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    ($this->makePaymentService)($mockStripe)->cancelPendingPayment($payment);

    expect($capturedOpts['stripe_account'])->toBe('acct_cancel');
    expect($payment->fresh()->status)->toBe('cancelled');
});
```

- [ ] **Step 2: Eseguire il test per verificare che fallisce**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "cancelPendingPayment passa stripe_account"
```

Atteso: FAIL.

- [ ] **Step 3: Aggiornare `cancelPendingPayment`**

```php
public function cancelPendingPayment(Payment $payment): void
{
    if ($payment->status !== 'pending') {
        return;
    }

    if ($payment->payment_method === 'stripe' && $payment->stripe_transaction_id) {
        try {
            $opts = $payment->stripe_account_id
                ? ['stripe_account' => $payment->stripe_account_id]
                : [];
            $this->stripe->paymentIntents->cancel($payment->stripe_transaction_id, [], $opts);
        } catch (\Throwable) {
            // PaymentIntent già cancellato o scaduto: nessuna azione necessaria
        }
    }

    $payment->update(['status' => 'cancelled']);
    PaymentRefunded::dispatch($payment);
}
```

- [ ] **Step 4: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Atteso: tutti PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(payments): add stripe_account option to cancelPendingPayment"
```

---

### Task 4: PaymentService — loyalty discount con `stripe_account` e ricalcolo `application_fee_amount`

**Files:**
- Modify: `app/Services/PaymentService.php:180-208`

**Interfaces:**
- Produce: `applyLoyaltyDiscount()` e `removeLoyaltyDiscount()` passano `stripe_account` e ricalcolano `application_fee_amount` proporzionalmente

- [ ] **Step 1: Aggiungere test per `applyLoyaltyDiscount`**

```php
it('applyLoyaltyDiscount ricalcola application_fee_amount e passa stripe_account', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'       => $appointment->id,
        'stripe_transaction_id' => 'pi_loyalty_test',
        'stripe_account_id'    => 'acct_loyalty',
        'platform_fee_percent' => 5.0,
        'status'               => 'pending',
    ]);

    $capturedParams = null;
    $capturedOpts   = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('update')
        ->once()
        ->withArgs(function ($id, $params, $opts) use (&$capturedParams, &$capturedOpts) {
            $capturedParams = $params;
            $capturedOpts   = $opts;
            return true;
        });

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    // 20% sconto su 50€: nuovo importo 40€, fee 5% = 2€
    ($this->makePaymentService)($mockStripe)->applyLoyaltyDiscount($payment, 20, 50.0);

    expect($capturedParams['amount'])->toBe(4000);
    expect($capturedParams['application_fee_amount'])->toBe(200);
    expect($capturedOpts['stripe_account'])->toBe('acct_loyalty');
});
```

- [ ] **Step 2: Aggiungere test per `removeLoyaltyDiscount`**

```php
it('removeLoyaltyDiscount ripristina application_fee_amount e passa stripe_account', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'          => $appointment->id,
        'stripe_transaction_id'    => 'pi_remove_loyalty',
        'stripe_account_id'       => 'acct_loyalty',
        'platform_fee_percent'    => 5.0,
        'loyalty_original_amount' => 50.0,
        'status'                  => 'pending',
    ]);

    $capturedParams = null;
    $capturedOpts   = null;
    $mockPaymentIntents = Mockery::mock();
    $mockPaymentIntents->shouldReceive('update')
        ->once()
        ->withArgs(function ($id, $params, $opts) use (&$capturedParams, &$capturedOpts) {
            $capturedParams = $params;
            $capturedOpts   = $opts;
            return true;
        });

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('paymentIntents')->andReturn($mockPaymentIntents);

    ($this->makePaymentService)($mockStripe)->removeLoyaltyDiscount($payment);

    expect($capturedParams['amount'])->toBe(5000);
    expect($capturedParams['application_fee_amount'])->toBe(250);
    expect($capturedOpts['stripe_account'])->toBe('acct_loyalty');
});
```

- [ ] **Step 3: Eseguire i test per verificare che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "loyalty"
```

Atteso: FAIL.

- [ ] **Step 4: Aggiornare `applyLoyaltyDiscount` e `removeLoyaltyDiscount`**

```php
public function applyLoyaltyDiscount(Payment $payment, int $percentage, float $originalAmount): void
{
    $discounted    = round($originalAmount * (1 - $percentage / 100), 2);
    $newAmountCents = (int) round($discounted * 100);

    $updateParams = ['amount' => $newAmountCents];
    if ($payment->stripe_account_id && $payment->platform_fee_percent) {
        $updateParams['application_fee_amount'] = (int) round($newAmountCents * $payment->platform_fee_percent / 100);
    }

    $opts = $payment->stripe_account_id
        ? ['stripe_account' => $payment->stripe_account_id]
        : [];

    $this->stripe->paymentIntents->update($payment->stripe_transaction_id, $updateParams, $opts);

    $payment->update([
        'amount'                      => $discounted,
        'loyalty_discount_percentage' => $percentage,
        'loyalty_original_amount'     => $originalAmount,
    ]);
}

public function removeLoyaltyDiscount(Payment $payment): void
{
    $original      = (float) $payment->loyalty_original_amount;
    $originalCents = (int) round($original * 100);

    $updateParams = ['amount' => $originalCents];
    if ($payment->stripe_account_id && $payment->platform_fee_percent) {
        $updateParams['application_fee_amount'] = (int) round($originalCents * $payment->platform_fee_percent / 100);
    }

    $opts = $payment->stripe_account_id
        ? ['stripe_account' => $payment->stripe_account_id]
        : [];

    $this->stripe->paymentIntents->update($payment->stripe_transaction_id, $updateParams, $opts);

    $payment->update([
        'amount'                      => $original,
        'loyalty_discount_percentage' => null,
        'loyalty_original_amount'     => null,
    ]);
}
```

- [ ] **Step 5: Eseguire i test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Atteso: tutti PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(payments): recalculate application_fee_amount in loyalty discount, add stripe_account option"
```

---

### Task 5: PaymentService — `handleStripeWebhook` multi-account

**Files:**
- Modify: `app/Services/PaymentService.php:74-111`
- Modify: `tests/Feature/Services/PaymentServiceTest.php`

**Interfaces:**
- Produce: `handleStripeWebhook(array $payload, ?string $accountId = null)` — usa `withoutGlobalScopes()` + filtra `stripe_account_id` se `$accountId` fornito

- [ ] **Step 1: Aggiungere test**

```php
it('handleStripeWebhook usa stripe_account_id nel lookup quando accountId fornito', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);

    // Payment di un altro business con stesso pi_ id (simulazione multi-tenant)
    $otherBusiness = \App\Models\Business::factory()->create();
    $decoy = \App\Models\Payment::factory()->create([
        'appointment_id'       => Appointment::factory()->create(['business_id' => $otherBusiness->id]),
        'stripe_transaction_id' => 'pi_multi_test',
        'stripe_account_id'    => 'acct_other',
        'status'               => 'pending',
    ]);

    $target = \App\Models\Payment::factory()->create([
        'appointment_id'       => $appointment->id,
        'stripe_transaction_id' => 'pi_multi_test',
        'stripe_account_id'    => 'acct_target',
        'status'               => 'pending',
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    ($this->makePaymentService)($mockStripe)->handleStripeWebhook([
        'type' => 'payment_intent.succeeded',
        'data' => ['object' => ['id' => 'pi_multi_test', 'latest_charge' => 'ch_target']],
    ], 'acct_target');

    expect($target->fresh()->status)->toBe('completed');
    expect($decoy->fresh()->status)->toBe('pending');
});
```

- [ ] **Step 2: Eseguire il test per verificare che fallisce**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "usa stripe_account_id nel lookup"
```

Atteso: FAIL.

- [ ] **Step 3: Aggiornare la firma e il lookup in `handleStripeWebhook`**

```php
public function handleStripeWebhook(array $payload, ?string $accountId = null): void
{
    $type = $payload['type'] ?? '';
    $transactionId = $payload['data']['object']['id'] ?? null;

    if (! $transactionId) {
        Log::warning('PaymentService: webhook payload missing transaction ID', ['type' => $payload['type'] ?? 'unknown']);
        return;
    }

    $query = Payment::withoutGlobalScopes()->where('stripe_transaction_id', $transactionId);
    if ($accountId !== null) {
        $query->where('stripe_account_id', $accountId);
    }
    $payment = $query->first();

    if (! $payment) {
        return;
    }

    if ($type === 'payment_intent.succeeded') {
        $chargeId = $payload['data']['object']['latest_charge'] ?? null;
        $appFeeId = $payload['data']['object']['application_fee'] ?? null;
        if ($chargeId) {
            $updates = ['stripe_charge_id' => $chargeId];
            if ($appFeeId) {
                $updates['stripe_application_fee_id'] = $appFeeId;
            }
            $payment->update($updates);
        }
        $this->markPaymentCompleted($payment);
    } elseif ($type === 'payment_intent.payment_failed') {
        $payment->update(['status' => 'failed']);
        PaymentRefunded::dispatch($payment);
    } elseif ($type === 'payment_intent.canceled') {
        $payment->update(['status' => 'cancelled']);
        PaymentRefunded::dispatch($payment);
    } elseif ($type === 'charge.refunded') {
        app(RefundService::class)->handleExternalRefund($payload['data']['object'], $accountId);
    }
}
```

- [ ] **Step 4: Eseguire tutti i test PaymentService**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Atteso: tutti PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat(payments): add accountId param to handleStripeWebhook for multi-tenant lookup"
```

---

### Task 6: RefundService — direct charges

**Files:**
- Modify: `app/Services/RefundService.php:15-61`
- Modify: `tests/Feature/Services/RefundServiceTest.php`

**Interfaces:**
- Produce: `refund()` rimuove `reverse_transfer`, aggiunge `stripe_account` option
- Produce: `handleExternalRefund(array $chargePayload, ?string $accountId = null)` — usa `withoutGlobalScopes()` + `stripe_account_id` filter

- [ ] **Step 1: Aggiungere test per `refund` direct charge**

```php
it('refund non usa reverse_transfer e passa stripe_account per direct charge', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'stripe_account_id' => 'acct_refund',
        'stripe_charge_id'  => 'ch_direct_refund',
        'status'            => 'completed',
        'amount'            => 50.0,
    ]);

    $fakeRefund = \Stripe\Refund::constructFrom([
        'id'     => 're_direct',
        'amount' => 5000,
        'status' => 'succeeded',
        'reason' => null,
    ]);

    $capturedParams = null;
    $capturedOpts   = null;
    $mockRefunds = Mockery::mock();
    $mockRefunds->shouldReceive('create')
        ->once()
        ->withArgs(function ($params, $opts) use (&$capturedParams, &$capturedOpts) {
            $capturedParams = $params;
            $capturedOpts   = $opts;
            return true;
        })
        ->andReturn($fakeRefund);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    $mockStripe->shouldReceive('getService')->with('refunds')->andReturn($mockRefunds);

    (new \App\Services\RefundService($mockStripe))->refund($payment);

    expect(array_key_exists('reverse_transfer', $capturedParams))->toBeFalse();
    expect($capturedParams['refund_application_fee'])->toBeTrue();
    expect($capturedOpts['stripe_account'])->toBe('acct_refund');
});
```

- [ ] **Step 2: Aggiungere test per `handleExternalRefund` con accountId**

```php
it('handleExternalRefund filtra per stripe_account_id quando accountId fornito', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);

    $otherBusiness = \App\Models\Business::factory()->create();
    $decoy = \App\Models\Payment::factory()->create([
        'appointment_id'    => Appointment::factory()->create(['business_id' => $otherBusiness->id]),
        'stripe_charge_id'  => 'ch_external_shared',
        'stripe_account_id' => 'acct_other',
        'status'            => 'completed',
        'amount'            => 50.0,
    ]);

    $target = \App\Models\Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'stripe_charge_id'  => 'ch_external_shared',
        'stripe_account_id' => 'acct_target',
        'status'            => 'completed',
        'amount'            => 50.0,
    ]);

    $mockStripe = Mockery::mock(\Stripe\StripeClient::class);
    (new \App\Services\RefundService($mockStripe))->handleExternalRefund([
        'id'      => 'ch_external_shared',
        'refunds' => ['data' => [['id' => 're_ext_001', 'amount' => 5000, 'status' => 'succeeded']]],
    ], 'acct_target');

    expect($target->fresh()->status)->toBe('refunded');
    expect($decoy->fresh()->status)->toBe('completed');
});
```

- [ ] **Step 3: Eseguire i test per verificare che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/RefundServiceTest.php --filter "reverse_transfer|handleExternalRefund filtra"
```

Atteso: FAIL.

- [ ] **Step 4: Aggiornare `RefundService`**

```php
public function refund(Payment $payment, ?int $amountCents = null): StripeRefund
{
    if (! $this->stripe) {
        throw new BookingException('Stripe non configurato. Verifica la chiave STRIPE_SECRET_KEY.');
    }

    if ($payment->status !== 'completed') {
        throw new BookingException('Solo i pagamenti completati possono essere rimborsati.');
    }

    if (empty($payment->stripe_charge_id)) {
        throw new \App\Exceptions\BookingException('Impossibile rimborsare: charge ID non ancora disponibile. Riprovare tra qualche istante.');
    }

    $params = ['charge' => $payment->stripe_charge_id];
    $opts   = [];

    if ($payment->stripe_account_id !== null) {
        $params['refund_application_fee'] = true;
        $opts['stripe_account']           = $payment->stripe_account_id;
    }

    if ($amountCents !== null) {
        $params['amount'] = $amountCents;
    }

    $stripeRefund = $this->stripe->refunds->create($params, $opts);

    $isConnect = $payment->stripe_account_id !== null;

    $refundRecord = StripeRefund::create([
        'payment_id'             => $payment->id,
        'stripe_refund_id'       => $stripeRefund->id,
        'amount'                 => $stripeRefund->amount,
        'status'                 => $stripeRefund->status,
        'reason'                 => $stripeRefund->reason ?? null,
        'refund_application_fee' => $isConnect,
        'reverse_transfer'       => false,
        'payload'                => $stripeRefund->toArray(),
    ]);

    if ($stripeRefund->status === 'succeeded' && $amountCents === null) {
        $payment->update(['status' => 'refunded']);
        PaymentRefunded::dispatch($payment);
    }

    return $refundRecord;
}

public function handleExternalRefund(array $chargePayload, ?string $accountId = null): void
{
    $chargeId = $chargePayload['id'] ?? null;
    if (! $chargeId) {
        return;
    }

    $query = Payment::withoutGlobalScopes()->where('stripe_charge_id', $chargeId);
    if ($accountId !== null) {
        $query->where('stripe_account_id', $accountId);
    }
    $payment = $query->first();
    if (! $payment) {
        return;
    }

    $refunds = $chargePayload['refunds']['data'] ?? [];
    $alreadyMarkedRefunded = false;
    foreach ($refunds as $refundData) {
        $refundId = $refundData['id'] ?? null;
        if (! $refundId || StripeRefund::where('stripe_refund_id', $refundId)->exists()) {
            continue;
        }

        StripeRefund::create([
            'payment_id'             => $payment->id,
            'stripe_refund_id'       => $refundId,
            'amount'                 => $refundData['amount'],
            'status'                 => $refundData['status'] ?? 'succeeded',
            'reason'                 => $refundData['reason'] ?? null,
            'refund_application_fee' => false,
            'reverse_transfer'       => false,
            'payload'                => $refundData,
        ]);

        if (! $alreadyMarkedRefunded) {
            $totalRefunded = StripeRefund::where('payment_id', $payment->id)->sum('amount');
            if ($totalRefunded >= (int) round((float) $payment->amount * 100)) {
                $payment->update(['status' => 'refunded']);
                PaymentRefunded::dispatch($payment);
                $alreadyMarkedRefunded = true;
            }
        }
    }
}
```

- [ ] **Step 5: Eseguire i test RefundService**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Services/RefundServiceTest.php
```

Atteso: tutti PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/RefundService.php tests/Feature/Services/RefundServiceTest.php
git commit -m "feat(payments): update RefundService for direct charges, add accountId to handleExternalRefund"
```

---

### Task 7: Frontend — `stripeAccount` in Stripe.js

**Files:**
- Modify: `app/Http/Controllers/Portal/AppointmentController.php:147-159`
- Modify: `resources/views/portal/appointments/payment.blade.php:83-85`
- Modify: `resources/js/app.js:56`

**Interfaces:**
- Consume: `Payment.stripe_account_id`
- Produce: Stripe.js inizializzato con `{ stripeAccount }` quando il PI è un direct charge

- [ ] **Step 1: Aggiungere `stripeAccountId` al return della view in `AppointmentController`**

Trovare la riga `'clientSecret' => $payment->stripe_response['client_secret'] ?? null,` e aggiungere sotto:

```php
'stripeAccountId' => $payment->stripe_account_id,
```

- [ ] **Step 2: Aggiungere `data-stripe-account` al form in `payment.blade.php`**

Trovare:
```blade
<form data-stripe-payment data-public-key="{{ $stripePublicKey }}" data-client-secret="{{ $clientSecret }}" class="space-y-4">
```

Sostituire con:
```blade
<form data-stripe-payment data-public-key="{{ $stripePublicKey }}" data-client-secret="{{ $clientSecret }}" data-stripe-account="{{ $stripeAccountId ?? '' }}" class="space-y-4">
```

- [ ] **Step 3: Aggiornare l'inizializzazione di Stripe.js in `app.js`**

Trovare:
```js
const stripe = window.Stripe(stripeForm.dataset.publicKey);
```

Sostituire con:
```js
const stripeAccountId = stripeForm.dataset.stripeAccount;
const stripeOptions = stripeAccountId ? { stripeAccount: stripeAccountId } : {};
const stripe = window.Stripe(stripeForm.dataset.publicKey, stripeOptions);
```

- [ ] **Step 4: Build assets**

```bash
docker-compose run --rm --no-deps app npm run build
```

Atteso: build completata senza errori.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Portal/AppointmentController.php \
        resources/views/portal/appointments/payment.blade.php \
        resources/js/app.js \
        public/build/
git commit -m "feat(frontend): initialize Stripe.js with stripeAccount for direct charges"
```

---

### Task 8: StripeConnectWebhookController — routing payment events

**Files:**
- Modify: `app/Http/Controllers/StripeConnectWebhookController.php`
- Modify: `tests/Feature/Http/StripeConnectWebhookTest.php`

**Interfaces:**
- Consume: `PaymentService::handleStripeWebhook(array $payload, ?string $accountId)` (Task 5)
- Consume: `RefundService::handleExternalRefund(array $chargePayload, ?string $accountId)` (Task 6)
- Produce: Connect webhook instrada `payment_intent.*` e `charge.refunded` ai service layer

- [ ] **Step 1: Aggiungere test per `payment_intent.succeeded` via Connect webhook**

In `tests/Feature/Http/StripeConnectWebhookTest.php`:

```php
it('instrada payment_intent.succeeded al PaymentService con accountId', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'       => $appointment->id,
        'stripe_transaction_id' => 'pi_connect_evt',
        'stripe_account_id'    => 'acct_connect_evt',
        'status'               => 'pending',
    ]);

    $payload = [
        'id'      => 'evt_pi_connect',
        'type'    => 'payment_intent.succeeded',
        'account' => 'acct_connect_evt',
        'data'    => [
            'object' => [
                'id'             => 'pi_connect_evt',
                'latest_charge'  => 'ch_connect_001',
                'application_fee' => null,
            ],
        ],
    ];

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe('completed');
    expect($payment->fresh()->stripe_charge_id)->toBe('ch_connect_001');
});
```

- [ ] **Step 2: Aggiungere test per `charge.refunded` via Connect webhook**

```php
it('instrada charge.refunded al RefundService con accountId', function () {
    $appointment = Appointment::factory()->create(['business_id' => $this->business->id]);
    $payment = \App\Models\Payment::factory()->create([
        'appointment_id'    => $appointment->id,
        'stripe_charge_id'  => 'ch_refund_connect',
        'stripe_account_id' => 'acct_refund_connect',
        'status'            => 'completed',
        'amount'            => 50.0,
    ]);

    $payload = [
        'id'      => 'evt_charge_refunded',
        'type'    => 'charge.refunded',
        'account' => 'acct_refund_connect',
        'data'    => [
            'object' => [
                'id'      => 'ch_refund_connect',
                'refunds' => ['data' => [['id' => 're_connect_001', 'amount' => 5000, 'status' => 'succeeded']]],
            ],
        ],
    ];

    $response = $this->withoutMiddleware()
        ->postJson('/stripe/connect/webhook', $payload);

    $response->assertStatus(200);
    expect($payment->fresh()->status)->toBe('refunded');
});
```

- [ ] **Step 3: Eseguire i test per verificare che falliscono**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectWebhookTest.php --filter "instrada"
```

Atteso: FAIL.

- [ ] **Step 4: Aggiornare `StripeConnectWebhookController`**

Aggiungere dipendenze al costruttore e gestire i nuovi eventi:

```php
<?php

namespace App\Http\Controllers;

use App\Models\StripeConnectAccount;
use App\Models\StripeWebhookEvent;
use App\Services\PaymentService;
use App\Services\RefundService;
use App\Services\StripeConnectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeConnectWebhookController extends Controller
{
    public function __construct(
        private readonly StripeConnectService $connectService,
        private readonly PaymentService $paymentService,
        private readonly RefundService $refundService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.stripe.connect_webhook_secret');

        if (empty($secret)) {
            if (app()->isProduction()) {
                return response()->json(['error' => 'Webhook secret not configured'], 400);
            }
            $payload   = $request->all();
            $eventId   = $payload['id'] ?? null;
            $type      = $payload['type'] ?? null;
            $accountId = $payload['account'] ?? null;
        } else {
            try {
                $event = Webhook::constructEvent(
                    $request->getContent(),
                    $request->header('Stripe-Signature', ''),
                    $secret,
                );
            } catch (UnexpectedValueException|SignatureVerificationException) {
                return response()->json(['message' => 'Invalid signature.'], 400);
            }
            $payload   = $event->toArray();
            $eventId   = $event->id;
            $type      = $event->type;
            $accountId = $event->account ?? null;
        }

        if (! $eventId) {
            return response()->json(['received' => true]);
        }

        try {
            StripeWebhookEvent::create([
                'event_id'   => $eventId,
                'account_id' => $accountId,
                'type'       => $type,
                'payload'    => $payload,
            ]);
        } catch (\Illuminate\Database\UniqueConstraintViolationException) {
            return response()->json(['received' => true]);
        }

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
        } elseif (in_array($type, ['payment_intent.succeeded', 'payment_intent.payment_failed', 'payment_intent.canceled'])) {
            try {
                $this->paymentService->handleStripeWebhook($payload, $accountId);
                StripeWebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);
            } catch (\Throwable $e) {
                StripeWebhookEvent::where('event_id', $eventId)
                    ->update(['failed_at' => now(), 'error_message' => $e->getMessage()]);
            }
        } elseif ($type === 'charge.refunded') {
            try {
                $this->refundService->handleExternalRefund($payload['data']['object'], $accountId);
                StripeWebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);
            } catch (\Throwable $e) {
                StripeWebhookEvent::where('event_id', $eventId)
                    ->update(['failed_at' => now(), 'error_message' => $e->getMessage()]);
            }
        } else {
            StripeWebhookEvent::where('event_id', $eventId)->update(['processed_at' => now()]);
        }

        return response()->json(['received' => true]);
    }
}
```

- [ ] **Step 5: Eseguire i test Connect webhook**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Http/StripeConnectWebhookTest.php
```

Atteso: tutti PASS.

- [ ] **Step 6: Eseguire la suite completa**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Atteso: tutti PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/StripeConnectWebhookController.php tests/Feature/Http/StripeConnectWebhookTest.php
git commit -m "feat(webhook): route payment_intent and charge.refunded events in Connect webhook"
```

---

### Task 9: Stripe dashboard — aggiornare eventi Connect webhook (manuale)

- [ ] **Step 1: Aprire il Connect webhook su Stripe**

Andare su **Developers → Webhooks** → selezionare il webhook `https://booking-app.it/stripe/connect/webhook`.

- [ ] **Step 2: Aggiungere gli eventi**

Cliccare **Modifica destinazione** → **Aggiorna eventi** e aggiungere:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`

Confermare che il webhook sia di tipo **Account connessi**.

- [ ] **Step 3: Verificare con evento di test**

Sul Connect webhook, cliccare **Invia evento di test** per `payment_intent.succeeded`. Verificare nei log che il webhook risponda 200.

- [ ] **Step 4: Deploy**

```bash
make deploy
```
