# Stripe Connect — Infrastruttura Pagamenti per Salone

## Fase 0 — Verifica configurazione account Stripe (bloccante)

**Prima di scrivere migrazioni o codice onboarding**, verificare nel Dashboard Stripe e nella documentazione API corrente quale configurazione di account connesso è disponibile e consigliata per nuove integrazioni sulla piattaforma BookingApp.

Stripe sta deprecando i tipi "Standard / Express / Custom" per nuove integrazioni, raccomandando configurazioni basate su controller properties (Accounts v2), embedded components e hosted onboarding.

Questa fase è bloccante: la scelta impatta la struttura dell'account, le capability da richiedere, i componenti UI disponibili al salone e il comportamento del dashboard link.

Il flusso tecnico scelto resta valido indipendentemente dalla configurazione: **connected account creato dalla piattaforma con hosted onboarding via Account Links**.

---

## Obiettivo

Ogni salone che usa BookingApp può ricevere pagamenti online direttamente sul proprio account Stripe. BookingApp trattiene automaticamente una commissione di piattaforma su ogni transazione online.

I pagamenti dei clienti finali sono creati dalla piattaforma come destination charges e destinati automaticamente al connected account del salone. BookingApp non usa il proprio account come fallback né trattiene manualmente i fondi del salone, salvo la application fee configurata. Nota: con destination charges, il charge è tecnicamente creato sulla piattaforma e le Stripe fees sono a carico della piattaforma — questo deve essere considerato nel margine della platform fee.

---

## Modello pagamenti

### Destination charge

```
Cliente → paga sulla piattaforma BookingApp (platform Stripe key)
Stripe  → trasferisce automaticamente (importo - platform fee) al salone
Stripe  → trattiene platform fee su account BookingApp
Stripe  → addebita le proprie fees alla piattaforma
```

Parametri PaymentIntent:

```php
[
    'amount'                    => $amountInCents,
    'currency'                  => 'eur',
    'automatic_payment_methods' => ['enabled' => true],
    'on_behalf_of'              => $connectAccount->stripe_account_id,
    'application_fee_amount'    => $platformFeeInCents,
    'transfer_data'             => [
        'destination' => $connectAccount->stripe_account_id,
    ],
    'metadata' => [
        'business_id'    => $business->id,
        'appointment_id' => $appointment->id,
        'payment_id'     => $payment->id,
    ],
]
```

`on_behalf_of` indica il connected account come settlement merchant per la destination charge, quando supportato/necessario. Questo aiuta ad attribuire correttamente la transazione al salone e può influenzare statement descriptor, settlement e requisiti cross-border. Va usato insieme a `transfer_data.destination` nel nostro modello. Con alcune configurazioni cross-border, Stripe lo richiede esplicitamente.

### Regola netta: nessun fallback su BookingApp

Se un salone non ha un connected account abilitato, il pagamento online **non è disponibile**. Il booking wizard mostra solo "Paga in salone". Non esiste modalità di fallback sul conto BookingApp.

---

## Fee di piattaforma

### Priorità di calcolo

```
1. business.stripe_platform_fee_percent    (override per business, nullable)
2. SystemSetting stripe_platform_fee_percent (configurabile da super-admin)
3. env STRIPE_PLATFORM_FEE_PERCENT          (fallback)
4. 0                                        (default sicuro se nulla è configurato)
```

```
fee_percent = business.stripe_platform_fee_percent
           ?? SystemSetting::get('stripe_platform_fee_percent')
           ?? config('services.stripe.platform_fee_percent')
           ?? 0

fee_cents = round(amount_cents * fee_percent / 100)
```

Variabile d'ambiente globale:

```
STRIPE_PLATFORM_FEE_PERCENT=2.5
```

Override per business: `businesses.stripe_platform_fee_percent` (nullable decimal). Se null, si scala alla fonte successiva.

### Storicizzazione

La fee va salvata al momento della creazione del PaymentIntent, non ricalcolata in seguito:

```
payments.platform_fee_percent       decimal(5,2) nullable
payments.platform_fee_amount        integer      default 0   (centesimi)
payments.stripe_application_fee_id  string       nullable
payments.stripe_account_id          string       nullable    (snapshot del connected account)
payments.stripe_transfer_id         string       nullable
payments.stripe_charge_id           string       nullable
```

`payments.stripe_transaction_id` esiste già e mappa al `payment_intent_id`.

---

## Database

### Tabella `stripe_connect_accounts`

```sql
id                           bigint unsigned PK
business_id                  bigint unsigned FK businesses.id UNIQUE
stripe_account_id            string(255) UNIQUE nullable
mode                         enum('test','live') NOT NULL
status                       enum('pending','active','restricted','disabled') default 'pending'
charges_enabled              boolean default false
payouts_enabled              boolean default false
details_submitted            boolean default false
capabilities                 json nullable
requirements_currently_due   json nullable
requirements_past_due        json nullable
requirements_disabled_reason string(255) nullable
default_currency             char(3) nullable
country                      char(2) nullable
onboarding_completed_at      timestamp nullable
last_webhook_at              timestamp nullable
created_at / updated_at      timestamps
```

`capabilities` salva il payload capabilities restituito da Stripe (es. `card_payments`, `transfers`) per audit e debug.

### Colonne aggiuntive su `businesses`

```sql
stripe_platform_fee_percent  decimal(5,2) nullable
```

### Colonne aggiuntive su `payments`

```sql
platform_fee_percent         decimal(5,2) nullable
platform_fee_amount          integer default 0
stripe_application_fee_id    string(255) nullable
stripe_account_id            string(255) nullable
stripe_transfer_id           string(255) nullable
stripe_charge_id             string(255) nullable
```

### Tabella `stripe_refunds`

```sql
id                              bigint unsigned PK
payment_id                      bigint unsigned FK payments.id
stripe_refund_id                string(255) UNIQUE
amount                          integer           (centesimi)
status                          string(50)        (succeeded, pending, failed, canceled)
reason                          string(255) nullable
refund_application_fee          boolean default true
reverse_transfer                boolean default true
stripe_balance_transaction_id   string(255) nullable
payload                         json nullable
created_at / updated_at         timestamps
```

Serve per rimborsi parziali, prevenzione doppio rimborso, idempotenza webhook e customer support.

### Tabella `stripe_webhook_events`

```sql
id              bigint unsigned PK
event_id        string(255) UNIQUE
account_id      string(255) nullable
type            string(255)
payload         json
processed_at    timestamp nullable
failed_at       timestamp nullable
error_message   text nullable
created_at      timestamp
```

Se `event_id` esiste già: rispondere 200 senza rielaborare.

---

## Lifecycle del connected account

```
[non esistente]
     │
     │ admin clicca "Collega Stripe"
     ▼
[pending] ← stripe_account_id assegnato, details_submitted=false
     │
     │ utente completa onboarding su Stripe
     ▼
[pending, details_submitted=true] ← in revisione Stripe
     │
     │ webhook account.updated: charges_enabled=true
     ▼
[active] ← pagamenti online abilitati
     │
     │ webhook account.updated: requirements_past_due non vuoto
     ▼
[restricted] ← pagamenti sospesi
     │
     │ salone risolve requirements su Stripe
     ▼
[active]
```

`disabled` è stato terminale: Stripe ha chiuso l'account. Richiede intervento manuale.

---

## Onboarding — flusso tecnico

1. Admin salone clicca "Collega Stripe"
2. Backend crea un connected account vuoto tramite Stripe API (se non esiste già), richiedendo le capability necessarie (`card_payments`, `transfers` o equivalenti nella configurazione account scelta — da verificare in Fase 0)
3. Backend crea un `AccountLink` di tipo `account_onboarding` con `return_url` e `refresh_url`
4. Redirect utente all'URL dell'AccountLink (single-use, scade)
5. Stripe gestisce il form di raccolta dati (hosted)
6. Al termine, Stripe redirect a `return_url`: `/admin/stripe/callback`
7. Backend aggiorna `details_submitted = true`, status rimane `pending`
8. Webhook `account.updated` aggiorna `charges_enabled`, `payouts_enabled`, `capabilities`, `status`

Se l'utente abbandona il flusso, la `refresh_url` permette di generare un nuovo AccountLink e riprendere.

### Variabili d'ambiente

```
STRIPE_SECRET_KEY=sk_...
STRIPE_PUBLIC_KEY=pk_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_CONNECT_WEBHOOK_SECRET=whsec_...
STRIPE_PLATFORM_FEE_PERCENT=2.5
```

---

## Disponibilità "Paga ora" nel booking

"Paga ora" è visibile e selezionabile solo se **tutte** le seguenti condizioni sono vere:

```php
$connectAccount !== null
&& $connectAccount->stripe_account_id !== null
&& $connectAccount->charges_enabled === true
&& $connectAccount->status === 'active'
```

In qualsiasi altro caso, il wizard mostra solo "Paga in salone". Nessun messaggio di errore visibile al cliente finale — il metodo è semplicemente assente.

Il modello `Business` espone:

```php
$business->canAcceptOnlinePayments(): bool
```

---

## Webhook

### Due endpoint separati

| Endpoint | Scopo | Secret |
|---|---|---|
| `POST /stripe/webhook` | Pagamenti destination charge + eventi piattaforma | `STRIPE_WEBHOOK_SECRET` |
| `POST /stripe/connect/webhook` | Stato account connessi | `STRIPE_CONNECT_WEBHOOK_SECRET` |

Il Connect webhook va registrato nel Dashboard Stripe come "Connect endpoint" con la checkbox "Listen to events on Connected accounts".

I destination charges sono creati sulla piattaforma, quindi i relativi eventi (`payment_intent.succeeded`, `charge.refunded`, ecc.) arrivano sul webhook piattaforma, non su quello Connect.

### Distribuzione eventi

**`POST /stripe/webhook` — eventi piattaforma:**

| Evento | Azione |
|---|---|
| `payment_intent.succeeded` | Aggiorna `payments.status = completed`, `stripe_charge_id`, `stripe_application_fee_id`; conferma appuntamento |
| `payment_intent.payment_failed` | Aggiorna `payments.status = failed` |
| `charge.refunded` | Sincronizza rimborso se avviato fuori dal gestionale |
| `refund.updated` | Aggiorna `stripe_refunds.status` |
| `charge.dispute.created` | Notifica admin BookingApp |
| `charge.dispute.closed` | Aggiorna stato disputa |
| `application_fee.refunded` | Registra restituzione fee |

**`POST /stripe/connect/webhook` — eventi account connessi:**

| Evento | Azione |
|---|---|
| `account.updated` | Aggiorna `stripe_connect_accounts` (status, charges_enabled, payouts_enabled, capabilities, requirements) |
| `account.application.deauthorized` | Segna account come disconnesso |

---

## Rimborsi

### Flusso primario — dal gestionale BookingApp

```
Admin/staff clicca "Rimborsa" su un pagamento
    ↓
RefundService::refund($payment, $amount = null)
    ↓
Stripe API: crea Refund su stripe_charge_id
    con refund_application_fee=true   (default)
    con reverse_transfer=true         (default)
    ↓
Crea record stripe_refunds
Aggiorna payment.status = 'refunded' (o 'partially_refunded' per rimborsi parziali)
Aggiorna appointment.status se necessario
Reversal loyalty points se accrual era già avvenuto
Notifica cliente
```

`refund_application_fee=true` e `reverse_transfer=true` sono i default per rimborsi di servizi non erogati. Possono essere overridati esplicitamente (es. rimborso parziale commerciale dove si trattiene la fee), ma mai implicitamente.

**Gestione saldo insufficiente:** con destination charges, `reverse_transfer=true` recupera i fondi dal connected account. Se il connected account ha saldo insufficiente, Stripe fallisce il reverse transfer. Il `RefundService` deve gestire questo caso esplicitamente: loggare l'errore, segnalare all'admin BookingApp, non cambiare `payment.status` a `refunded` finché il rimborso non è confermato (`stripe_refunds.status = succeeded`).

### Flusso secondario — rimborso avviato fuori dal gestionale

Applicabile solo se la configurazione Connect scelta consente al salone di gestire destination charges dalla propria interfaccia Stripe (dashboard, embedded components). Non garantito in tutte le configurazioni.

```
Webhook charge.refunded arriva su /stripe/webhook
    ↓
RefundWebhookHandler: cerca payment per stripe_charge_id
    ↓
Crea/aggiorna stripe_refunds idempotente su stripe_refund_id
Se payment.status != 'refunded': aggiorna stato, log, notifica
```

Il flusso primario (da gestionale) resta quello corretto. Il webhook è sincronizzazione di fallback.

---

## Admin UI — Filament (salone)

Filament Page "Pagamenti online" nelle impostazioni del salone. Mostra uno stato progressivo:

### Stato: Non collegato
- Badge grigio "Non configurato"
- Spiegazione in 3 punti: cosa succede, commissione trattenuta, cosa vedono i clienti se non configurato
- CTA: "Collega Stripe" → avvia onboarding

### Stato: Onboarding incompleto (`details_submitted = false`, `stripe_account_id` presente)
- Badge giallo "Configurazione incompleta"
- Messaggio: hai avviato la configurazione ma non l'hai completata
- CTA: "Riprendi configurazione" → genera nuovo AccountLink e redirect

### Stato: In revisione (`details_submitted = true`, `charges_enabled = false`)
- Badge azzurro "In attesa di approvazione"
- Messaggio: Stripe sta verificando i dati, di solito poche ore
- Nessuna azione disponibile

### Stato: Attivo (`charges_enabled = true`, `status = active`)
- Badge verde "Attivo"
- Mostra: commissione piattaforma applicata, data attivazione
- CTA: "Gestisci account Stripe →" — link al flusso di gestione disponibile per la configurazione Connect scelta: hosted account update, embedded component, o dashboard/login link se supportato dalla configurazione account

### Stato: Ristretto (`status = restricted`, `requirements_past_due` non vuoto)
- Badge rosso "Account sospeso"
- Messaggio: Stripe richiede ulteriori informazioni
- CTA: "Risolvi su Stripe →" → genera AccountLink di tipo `account_update`

### Stato: Disabilitato (`status = disabled`)
- Badge rosso "Account disabilitato"
- Messaggio: contattare il supporto BookingApp

---

## Admin UI — Filament (super-admin BookingApp)

Sezione "Stripe Connect" nel pannello globale:

- Tabella di tutti i `stripe_connect_accounts`: salone, status badge, charges_enabled, payouts_enabled, last_webhook_at
- Filtri per status
- Azione per-riga: "Sincronizza da Stripe" → re-fetch Account object via API e aggiorna DB
- Configurazione fee globale: `SystemSetting stripe_platform_fee_percent` modificabile da UI (priorità 2 nella gerarchia fee)
- Override fee per business: colonna editabile nella tabella businesses (priorità 1)
- Totale commissioni: aggregato `SUM(platform_fee_amount)` sui pagamenti completed, filtrabile per periodo

---

## Ambiente test/live

- `local` e `staging`: Stripe test mode, account connessi test
- `production`: Stripe live mode, account connessi live

La colonna `stripe_connect_accounts.mode` registra in quale modalità è stato creato l'account. Non è esposta come toggle all'admin del salone.

Non si fanno migrazioni automatiche da test a live: ogni salone completa onboarding separatamente su staging e su produzione.

---

## Out of scope (MVP)

- Pagamenti in salone tramite Stripe Terminal
- Abbonamenti o pagamenti ricorrenti
- Commissioni su prenotazioni non pagate online (es. no-show fee)
- Multi-currency (solo EUR per ora)
- Payout scheduling personalizzato per salone
- Split payment tra più saloni su un singolo appuntamento
- Invoice automatiche Stripe per i saloni
- Gestione dispute lato salone (solo notifica admin BookingApp per MVP)
