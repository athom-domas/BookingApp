# Stripe Connect: Migrazione a Direct Charges

**Data:** 2026-07-02
**Stato:** Approvato

## Obiettivo

Migrare l'integrazione Stripe Connect dal modello **destination charges** al modello **direct charges**, in cui il PaymentIntent viene creato direttamente sul connected account del salone, e la piattaforma trattiene una `application_fee_amount`.

## Motivazione

Con destination charges:
- La piattaforma addebita il cliente
- Trasferisce i fondi al salone via `transfer_data.destination`
- La piattaforma appare sulla ricevuta
- La piattaforma è responsabile di rimborsi e contestazioni

Con direct charges (modello scelto):
- Il salone addebita il cliente direttamente
- Il salone appare sulla ricevuta
- Il salone è responsabile di rimborsi e contestazioni
- La piattaforma riceve una `application_fee_amount`
- Più adatto a un gestionale SaaS dove il cliente acquista da un salone specifico

---

## Architettura

### Flusso pagamento

```
Cliente
  │
  ├─ Frontend: Stripe.js inizializzato con { stripeAccount: connectAccountId }
  │
  └─ POST /portal/.../payment
       │
       ├─ PaymentService::initiateStripePayment()
       │    └─ stripe->paymentIntents->create($params, ['stripe_account' => $connectAccountId])
       │         ├─ amount: importo totale
       │         ├─ currency: eur
       │         └─ application_fee_amount: fee piattaforma
       │
       └─ Payment creato con stripe_account_id salvato

  Cliente conferma pagamento via Payment Element
       │
       └─ Stripe invia webhook al Connect endpoint (/stripe/connect/webhook)
            └─ StripeConnectWebhookController
                 ├─ Idempotenza via StripeWebhookEvent (già presente)
                 └─ Routing per: payment_intent.succeeded/failed/canceled, charge.refunded
                      └─ PaymentService::handleStripeWebhook()
                           └─ Lookup: stripe_transaction_id + stripe_account_id
```

### Flusso rimborso

```
Admin avvia rimborso
  └─ RefundService::refund()
       └─ stripe->refunds->create(
            { charge: stripe_charge_id, refund_application_fee: true },
            { stripe_account: connectAccountId }
          )
```

---

## Modifiche per componente

### 0. Client Stripe da usare

Per i direct charges, `PaymentService` deve usare la **platform key** (non la chiave standalone del salone da `IntegrationSetting`). La chiamata viene scoped al connected account tramite `['stripe_account' => ...]` come request option, non cambiando la chiave API.

Il binding `StripeClient::class` in `AppServiceProvider` è già stato aggiornato per fare fallback a `config('cashier.secret')` (platform key) quando non c'è chiave business. Il piano deve verificare che questo binding restituisca il client platform anche nel contesto Connect.

### 1. PaymentService — `initiateStripePayment`

**Prima (destination charge):**
```php
$intentParams['on_behalf_of']           = $connectAccount->stripe_account_id;
$intentParams['application_fee_amount'] = $fee['cents'];
$intentParams['transfer_data']          = ['destination' => $connectAccount->stripe_account_id];
$paymentIntent = $this->stripe->paymentIntents->create($intentParams);
```

**Dopo (direct charge):**
```php
$intentParams['application_fee_amount'] = $fee['cents'];
$paymentIntent = $this->stripe->paymentIntents->create(
    $intentParams,
    ['stripe_account' => $connectAccount->stripe_account_id]
);
```

Rimossi: `on_behalf_of`, `transfer_data`.
Aggiunto: secondo argomento `['stripe_account' => ...]`.

### 2. PaymentService — metodi su PaymentIntent esistenti

Tutti i metodi che operano su un PaymentIntent creato come direct charge devono passare `stripe_account` se presente:

- `confirmPayment()` — `paymentIntents->retrieve()` + salva `latest_charge` in `stripe_charge_id` se succeeded (evita che il pagamento risulti completato ma non rimborsabile prima dell'arrivo del webhook)
- `cancelPendingPayment()` — `paymentIntents->cancel()`
- `applyLoyaltyDiscount()` — `paymentIntents->update()`
- `removeLoyaltyDiscount()` — `paymentIntents->update()`

Pattern comune:
```php
$opts = $payment->stripe_account_id
    ? ['stripe_account' => $payment->stripe_account_id]
    : [];
$this->stripe->paymentIntents->retrieve($id, [], $opts);
```

### 3. PaymentService — loyalty discount e `application_fee_amount`

Quando si modifica `amount` di un PaymentIntent (sconto loyalty), si deve ricalcolare `application_fee_amount` proporzionalmente per evitare errori Stripe (la fee deve essere positiva e inferiore all'importo).

```php
// Esempio: sconto del 20% su 50€ con fee al 5%
// Nuovo amount: 4000 (40€)
// Nuova fee: round(4000 * $feePercent / 100) = 200 (2€)
```

Se il Payment non ha `platform_fee_percent`, si salta il ricalcolo della fee.

### 4. RefundService

**Prima:**
```php
$params = [
    'charge'                  => $payment->stripe_charge_id,
    'reverse_transfer'        => true,   // ← rimosso per direct charges
    'refund_application_fee'  => true,
];
$stripeRefund = $this->stripe->refunds->create($params);
```

**Dopo:**
```php
$params = [
    'charge'                 => $payment->stripe_charge_id,
    'refund_application_fee' => true,  // rimane: restituisce la fee alla piattaforma
];
$opts = $payment->stripe_account_id
    ? ['stripe_account' => $payment->stripe_account_id]
    : [];
$stripeRefund = $this->stripe->refunds->create($params, $opts);
```

`reverse_transfer` non è applicabile nei direct charges: il parametro esiste nell'API Refund, ma serve solo quando c'è un transfer da invertire (modello destination). Nei direct charges non viene creato alcun transfer.

### 5. Frontend — Stripe.js initialization

Il `client_secret` di un direct charge appartiene al connected account. Stripe.js deve essere inizializzato con `stripeAccount` altrimenti non riesce a confermarlo.

**payment.blade.php** — aggiungere attributo al form:
```blade
data-stripe-account="{{ $stripeAccountId ?? '' }}"
```

**app.js** — modificare inizializzazione:
```js
const stripeAccountId = stripeForm.dataset.stripeAccount;
const stripeOptions = stripeAccountId ? { stripeAccount: stripeAccountId } : {};
const stripe = window.Stripe(stripeForm.dataset.publicKey, stripeOptions);
```

Il controller che serve la view deve passare `$stripeAccountId` ricavato da `$payment->stripe_account_id` (il connected account su cui è stato creato quel PaymentIntent), non dal business corrente. Questo garantisce coerenza anche se il connected account del business cambiasse in futuro.

### 6. StripeConnectWebhookController — routing payment events

Con direct charges, gli eventi `payment_intent.*` e `charge.refunded` arrivano dal connected account al Connect webhook (con campo `event->account`).

**Eventi da aggiungere al handler:**
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`

**Idempotenza:** già gestita — il controller salva ogni evento in `StripeWebhookEvent` e ignora duplicati via `UniqueConstraintViolationException`.

**Lookup Payment:** usare `withoutGlobalScopes()` (il webhook Connect non ha contesto tenant) + `stripe_account_id` per sicurezza multi-tenant:
```php
Payment::withoutGlobalScopes()
    ->where('stripe_transaction_id', $transactionId)
    ->where('stripe_account_id', $accountId)
    ->first();
```

Attualmente `PaymentService::handleStripeWebhook` fa lookup solo per `stripe_transaction_id`. Va aggiornato per accettare un `$accountId` opzionale.

**`charge.refunded` è un caso speciale:** `data.object.id` è un `ch_...` (charge ID), non un `pi_...`. Il routing per questo evento deve usare `stripe_charge_id` + `stripe_account_id`:
```php
// Nel Connect webhook, per charge.refunded:
// 1. Lookup via stripe_charge_id (non stripe_transaction_id)
// 2. Delegare a RefundService::handleExternalRefund($chargePayload, $accountId)
Payment::withoutGlobalScopes()
    ->where('stripe_charge_id', $chargeId)
    ->where('stripe_account_id', $accountId)
    ->first();
```
`RefundService::handleExternalRefund` va aggiornato per accettare `$accountId` opzionale e usarlo nel lookup.

### 7. Stripe dashboard

Sul Connect webhook (`/stripe/connect/webhook`), aggiungere:
- `payment_intent.succeeded`
- `payment_intent.payment_failed`
- `payment_intent.canceled`
- `charge.refunded`

Il webhook deve essere di tipo **Account connessi** (già configurato così).

---

## Fuori scope

- **Google Pay / Payment Method Domains**: Google Pay è attualmente disabilitato nel frontend (`googlePay: 'never'`). La registrazione del dominio per ogni connected account va aggiunta quando Google Pay viene abilitato.
- **Idempotenza `StripeWebhookController`** (per-business): Con direct charges, i payment events dei saloni Connect arrivano via Connect webhook, non via per-business webhook. Il per-business webhook rimane rilevante solo per saloni con Stripe standalone. Idempotenza da aggiungere separatamente.
- **Migrazione pagamenti esistenti**: ambiente di test, nessun dato da migrare.
- **ProductOrderService**: il modulo prodotti crea PaymentIntent sulla piattaforma senza Connect (nessun `application_fee`, nessun `stripe_account_id` sull'ordine). La migrazione a direct charges per gli ordini prodotto è fuori scope — richiede migration + refactor separato. Va fatto in un task dedicato successivo.

---

## Vincoli

- Stripe PHP SDK: `^17.3.0`
- Laravel Cashier: `^16.0` (per billing piattaforma, non tocca i payment dei saloni)
- I pagamenti senza Stripe Connect (contanti, POS) non sono impattati
- Il fallback `hasConnect = false` in `PaymentService` rimane invariato
