# Calendario Appuntamenti — Design Spec

**Data:** 2026-05-18  
**Scope:** Pagina calendario nel pannello admin Filament 4 per visualizzare gli appuntamenti

---

## Obiettivo

Aggiungere una pagina separata nel menu admin con un calendario interattivo degli appuntamenti. Admin vede tutti gli appuntamenti con filtro per staff; staff vede solo i propri. Viste: mese, settimana, giorno. Click su evento apre popup con dettagli e azioni inline.

---

## Approccio

**`saade/filament-fullcalendar`** — package Composer che integra FullCalendar.js in Filament.

> **Nota:** verificare compatibilità con Filament 4 come primo passo dell'implementazione. Se non esiste un branch stabile per v4, il fallback è implementare la pagina con FullCalendar.js via npm (già configurato) senza il package — il design rimane identico.

---

## Architettura

### Componenti

**`App\Filament\Pages\AppointmentCalendar`**
- Filament `Page` dedicata, voce nel menu di navigazione con icona calendario
- Contiene il widget calendario
- Admin: mostra filtro staff (dropdown) che passa `staff_id` al widget
- Staff: nessun filtro, la pagina mostra direttamente i propri appuntamenti

**`App\Filament\Widgets\AppointmentCalendarWidget`**
- Implementa `InteractsWithEvents` del package
- Fornisce `fetchEvents(array $fetchInfo): array` — restituisce appuntamenti come eventi FullCalendar
- Espone proprietà Livewire `$staffFilter` (null = tutti) aggiornabile dalla Page
- Gestisce `openAppointment(int $id)` per aprire il modal al click su evento

### Flusso dati

```
Page (filtro staff) → dispatch → Widget.$staffFilter
Widget.fetchEvents() → query Appointment (con eager load servizi) → array eventi FullCalendar
JS eventClick → Livewire.call('openAppointment', id) → Modal Filament
```

---

## Dati degli eventi

Ogni evento FullCalendar restituito da `fetchEvents()`:

| Campo | Valore |
|-------|--------|
| `id` | `appointment->id` |
| `title` | `"Cliente – Servizio1, Servizio2"` |
| `start` | `scheduled_date` (ISO 8601) |
| `end` | `scheduled_date + somma duration_minutes dei servizi` |
| `backgroundColor` | colore dalla palette staff |
| `extendedProps.status` | stato appuntamento |

**Calcolo `end`:** `fetchEvents()` carica gli appuntamenti nel range, poi risolve i `service_ids` con una singola query `Service::whereIn('id', $allServiceIds)` per tutti gli appuntamenti del range — nessun N+1.

**Colori staff:** palette fissa di 8 colori hex. La mappa `staff_id → colore` viene calcolata una volta nel widget e assegnata in ordine agli utenti con ruolo `staff`.

---

## Filtro staff (solo admin)

- Dropdown nella Page con tutti gli utenti staff
- Selezionando un membro: `fetchEvents()` aggiunge `->where('staff_id', $staffFilter)`
- Valore nullo = tutti gli appuntamenti (default)
- Il filtro influenza solo la vista calendario, non persiste

---

## Access control

| Ruolo | Comportamento |
|-------|--------------|
| `admin` | Vede tutti gli appuntamenti; filtro staff visibile |
| `staff` | Vede solo `staff_id = auth()->id()`; filtro staff nascosto |

La `Page` è registrata nel panel provider. L'accesso è controllato con `canAccess()` se necessario (attualmente entrambi i ruoli hanno accesso all'admin).

---

## Popup evento (modal Filament)

Aperto da `openAppointment($id)` nel widget. Contenuto:

**Informazioni (read-only):**
- Cliente, servizi, staff, data/ora, stato (badge), note, prezzo e stato pagamento

**Azioni:**
- **Cambia stato** — `Action` con select (`pending / confirmed / completed / cancelled`), disponibile per admin e staff
- **Registra pagamento** — riusa la logica di `AppointmentResource::register_payment` (metodo + form identici); visibile solo se `payment->status !== 'completed'`
- **Modifica completa** — link a `AppointmentResource::edit/{id}`

---

## Testing

Tutti i test con Pest + `Livewire::test()`:

- **Accesso per ruolo:** admin riceve appuntamenti di tutti gli staff; staff riceve solo i propri
- **`fetchEvents()`:** verifica struttura evento (campi obbligatori presenti), calcolo corretto di `end`
- **Filtro staff:** con filtro attivo, admin riceve solo appuntamenti del membro selezionato
- **Cambio stato:** l'action aggiorna `status` nel DB
- **Registra pagamento:** crea record `Payment` con status `completed`
- **Nessun test JS** — il rendering FullCalendar è responsabilità del package

---

## File da creare

```
app/Filament/Pages/AppointmentCalendar.php
app/Filament/Widgets/AppointmentCalendarWidget.php
tests/Feature/Filament/AppointmentCalendarTest.php
```

Nessuna migrazione necessaria. Nessun nuovo modello.
