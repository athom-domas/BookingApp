# Programma fedeltà a punti — Design

**Data:** 2026-06-05

## Obiettivo

Permettere a ogni salone di attivare (opzionale) un programma fedeltà in cui i clienti
accumulano punti in base alla spesa e, raggiunta una soglia, sbloccano uno sconto
percentuale **una tantum** (voucher consumabile) applicato dall'admin al pagamento
successivo. L'intero sistema è inattivo finché l'admin non lo abilita.

## Decisioni di design (approvate)

- **Meccanica:** punti basati sulla spesa (`punti = floor(amount × punti_per_euro)`).
- **Premio:** una sola regola — `soglia` punti → sconto del `X%`.
- **Tipo sconto:** voucher consumabile. Al riscatto i punti `soglia` vengono scalati e
  il cliente riparte ad accumulare. Nessuno sconto permanente.
- **Applicazione sconto:** manuale, dall'admin, nel flusso di completamento appuntamento
  già esistente. Nessuna modifica al flusso pubblico di prenotazione né ai prezzi in vetrina.
- **Multi-tenant:** tutto scopato per salone tramite `BelongsToBusiness`.

## Fuori scope (per ora)

- Livelli/soglie multiple, catalogo premi.
- Riscatto self-service del voucher dal portale cliente o nel pagamento online Stripe
  (il riscatto avviene solo nel flusso admin in salone). L'**accumulo** invece vale per
  tutti i pagamenti completati, online inclusi.
- Esposizione del programma nella vetrina pubblica.
- Scadenza dei punti.

## Modello dati

### `loyalty_accounts`
Un record per cliente per salone (saldo corrente, denormalizzato per letture rapide).

| Colonna       | Tipo                | Note                                  |
|---------------|---------------------|---------------------------------------|
| `id`          | bigint PK           |                                       |
| `business_id` | FK → businesses     | auto da `BelongsToBusiness`           |
| `user_id`     | FK → users          | il cliente                            |
| `points`      | unsignedInteger     | saldo corrente, default 0             |
| timestamps    |                     |                                       |

Indice unico `(business_id, user_id)`.

### `loyalty_transactions`
Storico movimenti (ledger) per trasparenza e per ricostruire/verificare il saldo.

| Colonna              | Tipo                | Note                                          |
|----------------------|---------------------|-----------------------------------------------|
| `id`                 | bigint PK           |                                               |
| `business_id`        | FK → businesses     | auto                                          |
| `loyalty_account_id` | FK → loyalty_accounts |                                             |
| `appointment_id`     | FK → appointments nullable | origine del movimento                   |
| `type`               | string              | `earn` \| `redeem` \| `reverse`               |
| `points`             | integer (signed)    | positivo per earn, negativo per redeem/reverse|
| `description`        | string nullable     | es. "Pagamento appuntamento #123"             |
| timestamps           |                     |                                               |

Il saldo `loyalty_accounts.points` è sempre = somma di `loyalty_transactions.points`.
Tutte le scritture (accredito/riscatto/storno) avvengono in **transazione DB** che
aggiorna ledger + saldo insieme.

## Configurazione admin

Estensione di `SystemSetting` (stesso pattern di `reviews_enabled`):

| Chiave                      | Tipo    | Default | Significato                          |
|-----------------------------|---------|---------|--------------------------------------|
| `loyalty_enabled`           | boolean | `false` | attiva/disattiva il programma        |
| `loyalty_points_per_euro`   | integer | `1`     | punti accreditati per ogni € speso   |
| `loyalty_reward_threshold`  | integer | `100`   | punti necessari per lo sconto        |
| `loyalty_reward_percentage` | integer | `10`    | percentuale di sconto sbloccata      |

Getter statici da aggiungere (mirror di `isReviewsEnabled()`):
`isLoyaltyEnabled()`, `getLoyaltyPointsPerEuro()`, `getLoyaltyRewardThreshold()`,
`getLoyaltyRewardPercentage()`.

Nuova sezione "Fedeltà" nella pagina `SystemSettings` (Filament):
- Toggle `loyalty_enabled` (`->live()`).
- I tre campi numerici (`points_per_euro`, `reward_threshold`, `reward_percentage`)
  visibili solo se `loyalty_enabled` è attivo, con `minValue` sensati e
  `reward_percentage` tra 1 e 100.

## Logica

### Servizio `LoyaltyService`
Punto unico per le mutazioni dei punti. Metodi:

- `accrue(Appointment $appointment, float $amount): void`
  - Esce subito se `SystemSetting::isLoyaltyEnabled()` è `false`.
  - **Idempotente:** se esiste già una transaction `earn` per quell'`appointment_id`,
    non riaccredita (evita doppi accrediti su modifiche ripetute).
  - Calcola `points = floor($amount × points_per_euro)`; se `> 0`, in transazione DB
    crea/recupera l'account del cliente, inserisce una `earn` transaction e incrementa
    il saldo.

- `redeem(Appointment $appointment): int`
  - Restituisce la percentuale di sconto applicata (`0` se non riscattabile).
  - Esce con `0` se il programma è disattivo o il saldo cliente `< threshold`.
  - In transazione DB: inserisce una `redeem` transaction `-threshold` legata
    all'`appointment_id` e decrementa il saldo. Ritorna `reward_percentage`.

- `reverse(Appointment $appointment): void`
  - Annulla l'accredito di un appuntamento (per rimborso/cancellazione di un
    appuntamento già completato). Se esiste una `earn` per quell'appuntamento e non è
    già stata stornata, inserisce una `reverse` transaction di pari punti negativi e
    decrementa il saldo (clamp a 0).

### Accumulo — observer sui pagamenti
`PaymentObserver` registrato su `Payment`:

- `updated()`: se lo `status` è passato a `completed`, chiama
  `LoyaltyService::accrue($payment->appointment, $payment->amount)`.
  Questo copre **tutti** i percorsi perché sia `recordInPersonPayment()` sia il
  pagamento Stripe finiscono in `PaymentService::markPaymentCompleted()` che fa
  `->update(['status' => 'completed'])`.
- `updated()`: se lo `status` è passato a `refunded`/`cancelled` da `completed`,
  chiama `LoyaltyService::reverse($payment->appointment)`.

L'idempotenza è garantita dentro il service (controllo della transaction `earn`
esistente), quindi un `updated` ripetuto non crea problemi.

### Riscatto — flusso admin in `EditAppointment`
Quando l'admin completa un appuntamento registrando un pagamento in salone:

1. Nel form dell'appuntamento (`AppointmentResource`) si aggiunge un toggle
   **"Applica sconto fedeltà (X%)"**, visibile solo se:
   - `loyalty_enabled` è attivo, **e**
   - il cliente selezionato ha `points ≥ threshold`, **e**
   - non esiste già un pagamento completato per l'appuntamento.
   La label mostra la percentuale corrente (es. "Applica sconto fedeltà 10% — 100 punti").
2. In `EditAppointment::beforeSave()`, se il toggle è attivo e l'appuntamento sta
   passando a `completed`:
   - chiama `LoyaltyService::redeem($appointment)` → ottiene `percentage`;
   - riduce `payment_amount` di `percentage%` **prima** di chiamare
     `recordInPersonPayment(...)`.
   - L'accredito (via observer su payment completed) avviene poi sull'importo **netto**
     scontato. Ordine: prima riscatto, poi accredito sul netto.

Se il toggle non è attivo, nulla cambia rispetto a oggi.

### Portale cliente
In `portal.appointments.index` (o nella dashboard del portale) si aggiunge una card
"Fedeltà", mostrata solo se `loyalty_enabled`:
- saldo punti corrente del cliente;
- barra di avanzamento verso `threshold`;
- badge "Sconto X% disponibile" quando `points ≥ threshold`, con nota che si applica
  in salone al prossimo appuntamento.

Il controller (`Portal\AppointmentController::index`) carica l'account fedeltà del
cliente (o saldo 0 se assente) e i parametri di soglia/percentuale dalle impostazioni.

## Comportamento a feature spenta

- `loyalty_enabled = false`: nessun accredito (il service esce subito), card portale
  nascosta, toggle riscatto nascosto, campi di config nascosti. I dati storici restano
  in tabella ma inerti.

## Casi limite

- **Importo che dà 0 punti** (`floor < 1`): nessuna transaction creata.
- **Doppio salvataggio dell'appuntamento completato:** nessun doppio accredito
  (idempotenza su `earn` per `appointment_id`).
- **Rimborso/cancellazione di un completato:** storno dell'accredito via `reverse`.
  Lo sconto già riscattato **non** viene ripristinato (semplificazione accettata:
  il voucher è considerato consumato).
- **Cliente senza account:** creato al primo accredito; nel portale mostra saldo 0.
- **Cambio di `points_per_euro`/`threshold`/`percentage` da parte dell'admin:** vale
  d'ora in avanti; non ricalcola lo storico.

## Test (Pest, Feature)

- `loyalty_enabled=false` → completare un pagamento non crea transaction né account.
- `loyalty_enabled=true`, pagamento da 50€ con ratio 1 → account con 50 punti e una
  `earn`.
- Doppio `markPaymentCompleted`/salvataggio → resta una sola `earn`, saldo invariato.
- Rimborso di un pagamento completato → `reverse`, saldo torna a 0.
- Riscatto con `points ≥ threshold` → `redeem` di `-threshold`, saldo decrementato,
  `payment.amount` ridotto della percentuale, accredito calcolato sul netto.
- Riscatto con `points < threshold` → toggle non disponibile / `redeem` ritorna 0.
- Tenant isolation: i punti di un cliente non sono visibili/sommati attraverso saloni
  diversi.
