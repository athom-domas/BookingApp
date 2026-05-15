# Booking Wizard — Design Spec

**Date:** 2026-05-15
**Status:** Approved

## Overview

Redesign della pagina di prenotazione: da form sidebar con select dropdown a un wizard a 5 step con accordion sequenziali. La home page (`/`) diventa una landing page separata; la prenotazione si sposta su una view dedicata servita dalla stessa route `booking.index`.

---

## Pages & Routing

| Route | View | Controller |
|---|---|---|
| `GET /` | `welcome.blade.php` | `BookingController@index` (aggiornato) |
| `GET /` (dati) | `portal.booking` | `BookingController@index` (aggiornato) |

**Nota:** la route resta `booking.index`. `BookingController@index` continua a caricare servizi e staff, ma ora rende `portal/booking/index.blade.php` invece di `welcome`. La welcome page diventa una landing statica (hero + servizi in vetrina read-only + CTA "Prenota ora").

---

## Booking Wizard — 5 Step Accordion

Gli accordion sono sequenziali: lo step N+1 si sblocca solo quando lo step N è completato. L'utente può tornare a qualsiasi step completato per modificarlo; il reset è a cascata verso il basso (tutti gli step successivi tornano incompleti e i relativi valori vengono azzerati).

L'header di ogni accordion mostra un riepilogo inline quando collapsed (es. "Taglio, Barba · 50 min · €35,00").

### Step 1 · Scegli i servizi

- Grid di card (2 colonne desktop, 1 mobile)
- Ogni card: nome, descrizione breve, durata, prezzo
- Selezione multipla: click toglla la selezione (bordo colorato + checkmark)
- "Continua" attivo solo con ≥1 servizio selezionato
- Riepilogo collapsed: nomi servizi, durata cumulativa, prezzo totale

### Step 2 · Scegli l'operatore

- Prima card speciale: "Qualsiasi operatore disponibile"
- Card degli operatori filtrate: solo staff che può eseguire **tutti** i servizi selezionati nello step 1
- Selezione singola
- Reset trigger: cambio selezione in step 1 → azzera staffId, date, slot, paymentMethod

### Step 3 · Scegli data e ora

- Griglia calendario mensile con navigazione prev/next mese
- Date passate: sempre disabilitate
- Date senza disponibilità (per i servizi/staff selezionati): disabilitate (grigie)
- Date disponibili: cliccabili
- Data selezionata: evidenziata
- Quando data selezionata: row di badge orari sotto il calendario (es. `09:00`, `09:30`)
- Badge selezionato: evidenziato
- "Continua" attivo solo con data + slot selezionati
- Reset trigger: cambio staff in step 2 → azzera date e slot

### Step 4 · Metodo di pagamento

- Due card selezionabili (nessun default — l'utente deve scegliere):
  - **Paga ora** — pagamento online con Stripe al termine della prenotazione
  - **Paga in salone** — pagamento al momento del servizio
- "Continua" attivo solo dopo selezione
- Reset trigger: cambio data/slot in step 3 → azzera paymentMethod

### Step 5 · Riepilogo e conferma

- Box riepilogo: servizi, operatore, data/ora, durata totale, prezzo totale, metodo di pagamento
- Campo note (opzionale, max 1000 caratteri)
- Bottone primario:
  - `paymentMethod === 'online'` → "Prenota e vai al pagamento"
  - `paymentMethod === 'in_salon'` → "Conferma prenotazione"
- **Utente non autenticato:** al posto del form, CTA login/registrazione. La selezione già effettuata viene preservata in `sessionStorage` e ripristinata al ritorno.

---

## Backend Changes

### Nuovo endpoint API

```
GET /api/booking/available-dates
```

Parametri query:
- `serviceIds[]` (required) — array di ID servizi
- `staffId` (optional) — ID staff specifico; se assente = qualsiasi
- `month` (required) — formato `YYYY-MM`

Risposta:
```json
{
  "success": true,
  "data": ["2026-05-16", "2026-05-19", "2026-05-20"]
}
```

Implementato in `Api\BookingController` (nuovo metodo `getAvailableDates`), delegato all'`AppointmentService` esistente. Iterazione sui giorni lavorativi del mese, check disponibilità slot per ciascuno.

### StoreBookingRequest

Aggiunta validazione campo `payment_method`:
```php
'payment_method' => 'required|in:online,in_salon',
```

### BookingController@store (Portal)

```
payment_method === 'online'
  → flusso Stripe esistente
  → redirect portal.appointments.payment

payment_method === 'in_salon'
  → appointment creato con status confirmed
  → nessun record Payment creato
  → redirect portal.appointments.show con flash di conferma
```

### AppointmentService

Il metodo `bookAppointment` deve supportare un parametro `confirmImmediately: bool` che imposta lo status dell'appuntamento a `confirmed` invece di `pending`.

---

## Alpine.js State

Installazione: `npm install alpinejs` + `import Alpine from 'alpinejs'` in `app.js`.

Singolo `x-data` root sulla pagina booking:

```js
{
  // navigation
  step: 1,
  completed: [],        // step completati [1,2,3,4]

  // selections
  selectedServiceIds: [],
  staffId: null,        // null = qualsiasi operatore
  date: null,           // 'YYYY-MM-DD'
  slot: null,           // 'HH:MM'
  paymentMethod: null,  // 'online' | 'in_salon'

  // calendar
  calendarMonth: '',    // 'YYYY-MM'
  availableDates: [],   // date disponibili nel mese corrente
  loadingDates: false,

  // slots
  availableSlots: [],   // [{start_time, end_time}]
  loadingSlots: false,
}
```

**Computed (tramite getter Alpine):**
- `totalDuration` — somma `duration_minutes` dei servizi selezionati
- `totalPrice` — somma `price` dei servizi selezionati
- `filteredStaff` — staff filtrato per servizi selezionati
- `isStepOpen(n)` — step corrente o già completato

**Reset a cascata:**
```
goToStep(n):
  step = n
  // azzera tutti gli step > n
  if n <= 1: staffId = null
  if n <= 2: date = null, slot = null, availableSlots = []
  if n <= 3: paymentMethod = null
  completed = completed.filter(s => s < n)
```

---

## API Calls dal Frontend

| Azione | Endpoint | Trigger |
|---|---|---|
| Date disponibili mese | `GET /api/booking/available-dates` | Apertura step 3, navigazione mese |
| Slot orari per data | `GET /api/booking/slots` | Selezione data nel calendario |

---

## Error Handling

- Errori API calendario/slot: messaggio inline nell'accordion (non toast globale)
- Validazione server-side su submit: errori riportati via `@errors` Blade esistente
- Utente non autenticato tenta submit: redirect login con `intended` URL

---

## Testing

- Unit test `AppointmentService` per `confirmImmediately` flag
- Feature test `BookingController` per entrambi i `payment_method`
- Feature test nuovo endpoint `available-dates`
- Test esistenti `BookingPortalTest` da aggiornare per il nuovo request field
