# SaaS Billing — Design Spec

## Goal

Aggiungere un sistema di abbonamento software al gestionale: piano unico €29/mese, 14 giorni di prova gratuita senza carta di credito, cancellazione in qualsiasi momento.

## Architecture

Laravel Cashier (`laravel/cashier`) viene aggiunto come dipendenza. Il soggetto billable è il model `Business` (root multi-tenant). Il trial viene tracciato tramite `trial_ends_at` sul model `Business` (generic trial — nessuna subscription Stripe durante il trial). Stripe Checkout hosted gestisce il pagamento, eliminando la necessità di form personalizzati per carta. Un middleware `CheckSubscription` protegge tutte le route `/admin/*` eccetto la pagina billing.

## Tech Stack

- `laravel/cashier` (Stripe driver)
- Stripe Checkout Sessions (hosted)
- Filament 4 custom Page per la UI billing
- Stripe Dashboard: 1 Product + 1 Price ricorrente da creare manualmente

---

## Data Model

### Modifiche a `businesses`

| Colonna | Tipo | Note |
|---|---|---|
| `trial_ends_at` | timestamp, nullable | `now() + 14 giorni` alla creazione |
| `stripe_id` | string, nullable | Customer ID Stripe |
| `pm_type` | string, nullable | Tipo carta (visa, mastercard…) |
| `pm_last_four` | string, nullable | Ultime 4 cifre |
| `pm_expiration` | string, nullable | Scadenza |

### Nuove tabelle Cashier

- `subscriptions` — gestita da Cashier
- `subscription_items` — gestita da Cashier

### Business model

```php
use Laravel\Cashier\Billable;

class Business extends Model
{
    use Billable;

    public function hasAccess(): bool
    {
        return $this->onGenericTrial() || $this->subscribed('default');
    }
}
```

Alla creazione di ogni nuovo Business, `trial_ends_at = now()->addDays(14)` viene impostato (nel factory e nel controller/seeder di registrazione).

---

## Middleware — `CheckSubscription`

Registrato in `bootstrap/app.php` come middleware named `check.subscription`.

Applicato a tutte le route del pannello Filament **eccetto** `/admin/abbonamento` e le route di autenticazione Filament.

```php
if (! $business->hasAccess()) {
    if ($user->isAdmin()) {
        return redirect()->route('filament.admin.pages.billing');
    }
    // Staff e altri ruoli: pagina statica "accesso sospeso"
    abort(403, 'Il tuo account è sospeso. Contatta l\'amministratore del salone.');
}
```

**Nota:** la `BillingPage` mostra i pulsanti di azione (attiva/annulla) solo agli utenti con ruolo `admin`. Staff che accedono alla pagina vedono solo lo stato corrente.

---

## Filament Page — `BillingPage`

**Route:** `/admin/abbonamento`  
**File:** `app/Filament/Pages/BillingPage.php`  
**Accesso:** tutti i ruoli (admin, staff) — il middleware di bypass è sulla pagina stessa

### Stati UI

#### Trial attivo (`$business->onGenericTrial()`)
- Badge verde "Periodo di prova"
- "Il tuo periodo di prova termina il **[data]** (X giorni rimasti)"
- Pulsante primario: **Attiva abbonamento**

#### Abbonamento attivo (`$business->subscribed('default')` e non cancellato)
- Badge verde "Piano attivo"
- "Piano BookingApp — €29/mese"
- "Prossimo rinnovo: **[data]**"
- Pulsante secondario: **Annulla abbonamento** (con modal di conferma)

#### Cancellato ma non scaduto (`$business->subscription('default')?->onGracePeriod()`)
- Badge arancione "Abbonamento annullato"
- "Accesso garantito fino al **[data]**"
- Pulsante: **Riattiva abbonamento**

#### Scaduto / nessun piano attivo
- Banner rosso prominente
- "Il periodo di prova è terminato. Abbonati per continuare a usare BookingApp."
- Pulsante primario grande: **Abbonati ora — €29/mese**

### Azioni

**`checkout` action** — crea Stripe Checkout Session:
```php
$session = $business->newSubscription('default', config('cashier.price_id'))
    ->checkout([
        'success_url' => route('filament.admin.pages.billing') . '?checkout=success',
        'cancel_url'  => route('filament.admin.pages.billing') . '?checkout=cancelled',
    ]);

return redirect($session->url);
```

**`cancel` action** (con modal conferma):
```php
$business->subscription('default')->cancel();
// Filament notification: "Abbonamento annullato. Accesso fino al [data]."
```

**`resume` action** (durante grace period):
```php
$business->subscription('default')->resume();
```

Parametri query `?checkout=success` e `?checkout=cancelled` mostrano notifiche Filament all'arrivo.

---

## BillingController (helper per checkout)

Non necessario se le action Filament gestiscono direttamente la redirect. Le action sulla Page usano `redirect()` direttamente.

---

## Webhook

**Nuovo endpoint:** `POST /stripe/billing-webhook`  
**Controller:** `App\Http\Controllers\StripeBillingWebhookController` — estende `\Laravel\Cashier\Http\Controllers\WebhookController`

Cashier gestisce automaticamente:
- `customer.subscription.created`
- `customer.subscription.updated`
- `customer.subscription.deleted` (cancella la subscription nel DB)

Evento custom da gestire:
- `invoice.payment_failed` → notifica email all'utente admin del business

**Configurazione:**
```
STRIPE_PRICE_ID=price_xxx
STRIPE_BILLING_WEBHOOK_SECRET=whsec_xxx
```

`config/cashier.php`:
- `currency` = `eur`
- `price_id` = `env('STRIPE_PRICE_ID')`

---

## Routes

```php
// In routes/web.php
Route::post('/stripe/billing-webhook', StripeBillingWebhookController::class)
    ->name('cashier.webhook');
```

Il middleware CSRF va escluso per questo endpoint in `bootstrap/app.php`.

---

## BusinessFactory

```php
'trial_ends_at' => now()->addDays(14),
```

---

## Testing

- `Business::hasAccess()` — true durante trial, true con sub attiva, false dopo trial senza sub
- Middleware `CheckSubscription` — redirect corretto su access denied, pass-through su access granted
- BillingPage — render corretto per ciascuno dei 4 stati
- Checkout action — crea Stripe Checkout session (mock Cashier)
- Cancel action — chiama `subscription->cancel()`

---

## Super Admin — Monitoraggio abbonamenti

Il panel `/superadmin` (già esistente, `app/Filament/SuperAdmin/`) viene esteso per esporre la visibilità completa sullo stato billing di ogni business.

### Estensione `BusinessResource` (tabella)

Nuove colonne aggiunte alla table esistente:

| Colonna | Fonte | Valore |
|---|---|---|
| Stato abbonamento | `$record->subscriptionStatus()` | badge: Trial / Attivo / Grace period / Scaduto |
| Fine trial | `$record->trial_ends_at` | data formattata, visibile solo se non ancora abbonato |
| Prossimo rinnovo | `$record->subscription('default')?->asStripeSubscription()->current_period_end` | timestamp UNIX → data |
| Metodo di pagamento | `$record->pm_type` + `$record->pm_last_four` | es. "Visa ••••4242" |

`Business` ottiene un metodo helper:
```php
public function subscriptionStatus(): string
{
    if ($this->subscribed('default') && ! $this->subscription('default')->onGracePeriod()) {
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
```

### Nuove azioni su `BusinessResource`

**"Estendi trial"** (Action) — disponibile solo se lo stato è `trial` o `expired`:
- Modal con input numerico "Giorni aggiuntivi" (default 14)
- Imposta `$record->trial_ends_at = max(now(), $record->trial_ends_at)->addDays($days)`

**"Cancella abbonamento"** (Action) — disponibile solo se stato è `active`:
- Chiama `$record->subscription('default')->cancelNow()` (cancellazione immediata, uso eccezionale super-admin)

### Widget di riepilogo (nuovo `BillingOverviewWidget`)

File: `app/Filament/SuperAdmin/Widgets/BillingOverviewWidget.php`

Quattro statsoverview cards:
- **Trial attivi** — `Business::where('trial_ends_at', '>', now())->whereDoesntHave('subscriptions'...)->count()`
- **Abbonamenti attivi** — count subscriptions active
- **MRR** — abbonamenti attivi × €29
- **Scaduti senza abbonamento** — trial scaduto, nessuna subscription attiva

Widget registrato nel `SuperAdminPanelProvider` e mostrato nella dashboard `/superadmin`.

### BusinessProvisioningService

`app/Services/BusinessProvisioningService.php` (già esistente) — aggiungere:
```php
$business->update(['trial_ends_at' => now()->addDays(14)]);
```
dopo la creazione del business, così ogni nuovo salone creato dal super-admin parte con il trial.

---

## Pre-requisiti manuali (fuori dal codice)

1. Creare in Stripe Dashboard: Product "BookingApp" → Price €29/mese ricorrente
2. Copiare il `price_id` in `.env` come `STRIPE_PRICE_ID`
3. Registrare webhook `/stripe/billing-webhook` in Stripe Dashboard, copiare secret in `STRIPE_BILLING_WEBHOOK_SECRET`
4. Per test locali: `stripe listen --forward-to localhost/stripe/billing-webhook`
