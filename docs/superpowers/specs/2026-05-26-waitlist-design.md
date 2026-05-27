# Lista d'Attesa — Design Spec

**Data:** 2026-05-26
**Stato:** Approvato

---

## Obiettivo

Permettere ai clienti di iscriversi a una lista d'attesa quando non ci sono slot disponibili. Quando una cancellazione libera uno slot compatibile, il primo cliente in lista viene notificato con un link per completare la prenotazione entro una finestra di tempo configurabile (default 3 ore).

---

## 1. Database

### Tabella `waitlist_entries`

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `user_id` | bigint FK users | |
| `service_ids` | JSON | array di ID servizi, stesso pattern di `Appointment.service_ids` |
| `preferred_date_from` | date | |
| `preferred_date_to` | date | |
| `preferred_time_from` | time | |
| `preferred_time_to` | time | |
| `preferred_days` | JSON | es. `["monday","wednesday","friday"]` |
| `preferred_staff_id` | bigint FK users, nullable | null = qualsiasi operatore |
| `status` | enum | `waiting`, `notified`, `booked`, `expired`, `cancelled` |
| `offered_slot` | JSON nullable | `{"date": "...", "time": "...", "staff_id": ...}` |
| `offer_expires_at` | datetime nullable | |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### Modifica `system_settings`

Aggiunta colonna `waitlist_offer_timeout_minutes` (integer, default 180).

---

## 2. Modello `WaitlistEntry`

- `#[Fillable([...])]` con tutti i campi della tabella
- `casts()`:
  - `service_ids`, `preferred_days`, `offered_slot` → `array`
  - `preferred_date_from`, `preferred_date_to` → `date`
  - `offer_expires_at` → `datetime`
  - `status` → cast enum o stringa
- Relazioni: `user()` → `belongsTo(User::class)`, `preferredStaff()` → `belongsTo(User::class, 'preferred_staff_id')`
- Scope `waiting()` → `where('status', 'waiting')`

---

## 3. Algoritmo di matching

Eseguito in PHP su tutti gli entry con `status = waiting`, ordinati per `created_at` ASC.

Dati dell'appuntamento cancellato: `$apt->service_ids`, `$apt->scheduled_date`.

Un entry è compatibile se:

1. `array_intersect($entry->service_ids, $apt->service_ids)` non è vuoto
2. `$entry->preferred_date_from <= $apt->scheduled_date->toDateString() <= $entry->preferred_date_to`
3. `$entry->preferred_time_from <= $apt->scheduled_date->format('H:i') <= $entry->preferred_time_to`
4. `strtolower($apt->scheduled_date->dayName)` è in `$entry->preferred_days`
5. `$entry->preferred_staff_id === null` oppure `$entry->preferred_staff_id === $apt->staff_id`

Il primo risultato vince.

---

## 4. Event flow

### Listener: `MatchWaitlistOnCancellation`

```php
#[ListensTo(AppointmentCancelled::class)]
```

- Esegue il matching
- Se trova un entry compatibile: dispatcha `NotifyWaitlistCandidateJob`

### Job: `NotifyWaitlistCandidateJob`

Riceve: `WaitlistEntry $entry`, `array $slotInfo` (date, time, staff_id).

1. Imposta `status = notified`, `offered_slot = $slotInfo`, `offer_expires_at = now() + timeout`
2. Invia la notifica al cliente (email / SMS / WhatsApp in base a `UserPreference`)
3. Dispatcha `ExpireWaitlistOfferJob->delay($entry->offer_expires_at)`

### Job: `ExpireWaitlistOfferJob`

Riceve: `WaitlistEntry $entry`, `array $slotInfo`.

1. Ricarica l'entry dal DB; se `status !== 'notified'` termina (idempotente)
2. Reset: `status = waiting`, `offered_slot = null`, `offer_expires_at = null`
3. Esegue di nuovo il matching per lo stesso `$slotInfo`; se trova un entry successivo: dispatcha `NotifyWaitlistCandidateJob`

---

## 5. Notifiche

**Email: `WaitlistOfferMail`**
- Oggetto: "Posto disponibile! Prenota entro le [HH:mm]"
- Corpo: servizio, data/ora slot, link firmato
- Segue il template esistente degli altri Mailable del progetto

**SMS/WhatsApp** via `NotificationService` esistente:
> "Posto disponibile per [servizio] il [data] alle [ora]. Prenota entro le [HH:mm]: [link]"

**Canale** determinato da `UserPreference` del cliente (stessa logica reminder).

---

## 6. Route e controller per accettare l'offerta

```
GET /r/waitlist/{entry}/accetta
    middleware: signed
    controller: WaitlistOfferController@accept
```

Il controller:

1. Verifica firma + `offer_expires_at` non scaduto
2. Verifica che lo slot sia ancora libero (nessun `Appointment` confirmed/pending su stessa data+ora+staff)
3. **Se libero:** crea `Appointment` con i dati di `offered_slot`, segna entry `booked`, redirige al portale con messaggio di conferma
4. **Se occupato:** segna entry `expired`, mostra messaggio "Spiacente, il posto è già stato occupato"

---

## 7. Portal UI

### Pagina `/portal/waitlist` (`Portal/WaitlistController`)

- Lista entry attive del cliente autenticato (status, servizi, date/ore preferite, azioni)
- Form "Unisciti alla lista d'attesa":
  - Select servizi (multi)
  - Select operatore (opzionale — "Qualsiasi operatore" di default)
  - Date from/to
  - Fascia oraria from/to
  - Giorni della settimana (checkbox)
- Pulsante "Rimuovi" → `status = cancelled`

### Integrazione nel flusso di prenotazione

Se la ricerca slot non produce risultati, il portale mostra:
> "Nessuna disponibilità nel periodo selezionato. [Unisciti alla lista d'attesa →]"

Il link porta a `/portal/waitlist/create` pre-compilato con i servizi già selezionati.

---

## 8. Admin UI (Filament)

### `WaitlistEntryResource`

- Lista con colonne: cliente, servizi, operatore preferito, date preferite, fascia oraria, giorni, status, data iscrizione
- Filtri per status e servizio
- Sola lettura in v1

### `SystemSettings` — nuovo campo

Sezione "Calendario e prenotazioni":
- Campo `waitlist_offer_timeout_minutes` (intero, min 30, default 180, suffisso "min")
- Label: "Timeout offerta lista d'attesa"

---

## Fuori scope (v1)

- Notifica a tutti contemporaneamente (batch)
- Integrazione Google Calendar per lo slot offerto
- Export lista d'attesa
