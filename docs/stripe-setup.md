# Stripe Setup — Booking App

Configurazione completa per ricevere pagamenti di abbonamenti in produzione.

---

## 1 — Chiavi API

1. Vai su **Developers → API keys** nel dashboard Stripe
2. Assicurati di essere in modalità **Live**
3. Copia la **Publishable key** (`pk_live_...`) → `STRIPE_PUBLIC_KEY`
4. Copia la **Secret key** (`sk_live_...`) → `STRIPE_SECRET_KEY`

---

## 2 — Prodotto e prezzo

1. Vai su **Product catalog → Add product**
2. Nome del piano (es. "Piano Base Salone")
3. Prezzo: **Recurring**, EUR, importo e cadenza mensile
4. Copia il **Price ID** (`price_...`) → `STRIPE_PRICE_ID`

---

## 3 — Webhook billing

Abbonamenti piattaforma — gestito da Laravel Cashier.

1. **Developers → Webhooks → Add destination**
2. Tipo: **Il tuo account** (non "Account connessi")
3. URL: `https://booking-app.it/stripe/billing-webhook`
4. Events: seleziona il gruppo **Abbonamenti** (18 eventi — include `subscription.*`, `invoice.*`, `customer.*`). Se il gruppo non è disponibile, seleziona manualmente:
   - `customer.subscription.created`
   - `customer.subscription.updated`
   - `customer.subscription.deleted`
   - `customer.subscription.trial_will_end`
   - `invoice.payment_succeeded`
   - `invoice.payment_failed`
   - `invoice.payment_action_required`
   - `payment_method.automatically_updated`
5. Copia il **Signing secret** → `STRIPE_BILLING_WEBHOOK_SECRET`

---

## 4 — Webhook Connect

Account connessi — saloni registrati come connected accounts.

1. **Developers → Webhooks → Add destination**
2. Tipo: **Account connessi**
3. URL: `https://booking-app.it/stripe/connect/webhook`
4. Events: **Accounts → account.updated**
5. Copia il **Signing secret** → `STRIPE_CONNECT_WEBHOOK_SECRET`

---

## 5 — Webhook pagamenti

Transazioni clienti — appuntamenti e ordini prodotti.

1. **Developers → Webhooks → Add destination**
2. Tipo: **Il tuo account**
3. URL: `https://booking-app.it/stripe/webhook`
4. Events (seleziona questi specifici):
   ```
   payment_intent.succeeded
   payment_intent.payment_failed
   payment_intent.canceled
   charge.refunded
   ```
5. Copia il **Signing secret** → `STRIPE_WEBHOOK_SECRET`

> Il secret di questo webhook può essere configurato anche per singolo salone dal pannello admin (IntegrationSetting). Il valore in `.env` è il fallback globale.

---

## 6 — .env.production

```env
STRIPE_PUBLIC_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_PRICE_ID=price_...
STRIPE_BILLING_WEBHOOK_SECRET=whsec_...
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

| Variabile | Fonte |
|-----------|-------|
| `STRIPE_PUBLIC_KEY` | Developers → API keys → Publishable key |
| `STRIPE_SECRET_KEY` | Developers → API keys → Secret key |
| `STRIPE_PRICE_ID` | Product catalog → prezzo abbonamento → Price ID |
| `STRIPE_BILLING_WEBHOOK_SECRET` | Webhook billing → Signing secret |
| `STRIPE_CONNECT_WEBHOOK_SECRET` | Webhook Connect → Signing secret |
| `STRIPE_WEBHOOK_SECRET` | Webhook pagamenti → Signing secret |

---

## 7 — Deploy

```bash
make deploy
```

Copia `.env.production` sul server, sincronizza il codice, lancia le migration e ricostruisce la config cache.

---

## 8 — Verifica

1. Su ogni webhook appena creato in Stripe: **Invia evento di test** → deve rispondere **200 OK**

2. Verifica che il secret billing sia letto correttamente:
   ```bash
   php85 artisan tinker --execute="var_dump(config('cashier.billing_webhook.secret'));"
   ```
   Deve restituire una stringa non vuota.

3. Verifica che l'abbonamento sia in DB dopo la creazione:
   ```bash
   php85 artisan tinker --execute="var_dump(\App\Models\Business::find(1)->subscriptions()->get()->toArray());"
   ```

### Se un abbonamento esiste su Stripe ma non in DB

```bash
php85 artisan tinker --execute="
\$business = \App\Models\Business::find(1);
\$stripe = \$business->stripe();
\$subs = \$stripe->subscriptions->all(['customer' => \$business->stripe_id, 'limit' => 5]);
var_dump(collect(\$subs->data)->map(fn(\$s) => [\$s->id, \$s->status])->toArray());
"
```

Recupera l'ID della subscription attiva, poi crea il record locale:

```bash
php85 artisan tinker --execute="
\$business = \App\Models\Business::find(1);
\$sub = \$business->stripe()->subscriptions->retrieve('sub_XXXXXXXX');
\$business->subscriptions()->create([
    'type'          => 'default',
    'stripe_id'     => \$sub->id,
    'stripe_status' => \$sub->status,
    'stripe_price'  => \$sub->items->data[0]->price->id,
    'quantity'      => \$sub->items->data[0]->quantity,
    'ends_at'       => null,
]);
"
```
