# Report Page — Admin Statistics

**Date:** 2026-05-19
**Scope:** Nuova pagina "Report" nel pannello admin Filament 4
**Stack:** Laravel 13, PHP 8.4, Filament 4, Chart.js (via Filament ChartWidget)

---

## Summary

Pagina dedicata alle statistiche del salone, accessibile solo agli admin via voce "Report" nella sidebar Filament. Mostra KPI card, grafici di trend e tabella performance staff, tutti filtrabili per periodo.

---

## Architecture

### Pagina

`app/Filament/Pages/ReportPage.php` estende `Filament\Pages\Page`. Registrata nel panel provider con accesso limitato agli admin (`canAccess()` controlla `$user->isAdmin()`).

La pagina mantiene due proprietà Livewire — `$dateFrom` e `$dateTo` — inizializzate al mese corrente. Un form Filament in cima alla pagina espone:
- Toggle periodo rapido: Oggi / Settimana / Mese / Anno (imposta automaticamente dateFrom/dateTo)
- Date range picker personalizzato (override manuale)

Il metodo `getWidgetData()` della pagina passa `['dateFrom' => ..., 'dateTo' => ...]` a tutti i widget registrati.

### Widget registrati (in ordine)

1. `RevenueStatsWidget` — KPI card riga 1
2. `InsightStatsWidget` — KPI card riga 2
3. `RevenueChartWidget` — grafico incassi nel tempo
4. `AppointmentsByStatusChartWidget` — grafico appuntamenti per stato
5. `ServiceBreakdownChartWidget` — grafico appuntamenti per servizio
6. `StaffPerformanceWidget` — tabella staff

Tutti in `app/Filament/Widgets/Reports/`.

---

## KPI Cards

### RevenueStatsWidget (riga 1)

| Stat | Query |
|------|-------|
| Incasso totale | `SUM(payments.payment_amount)` join appointments, status = `completed`, scheduled_at nel range |
| N° appuntamenti | `COUNT(appointments)` scheduled_at nel range (tutti gli stati) |
| Tasso cancellazione | `cancelled / totale * 100` — colore rosso se > 20% |
| Staff più produttivo | `users.name` con più appointments status = `completed` nel range; sottotitolo con conteggio |

### InsightStatsWidget (riga 2)

| Stat | Query |
|------|-------|
| Incasso medio/appuntamento | `incasso totale / COUNT(appointments con pagamento completed)` |
| Clienti unici | `COUNT(DISTINCT appointments.user_id)` nel range |
| Servizio più richiesto | `services.name` con più appuntamenti nel range; sottotitolo con conteggio |
| Appuntamenti in attesa | `COUNT(appointments)` status = `pending` — colore arancione |

---

## Charts

Tutti i `ChartWidget` ricevono `dateFrom`/`dateTo` e calcolano il raggruppamento temporale:
- Periodo ≤ 31 giorni → raggruppa per giorno
- Periodo > 31 giorni → raggruppa per settimana

### RevenueChartWidget (Line)

`SUM(payment_amount)` per slot temporale, solo pagamenti `completed`. Asse X: date, asse Y: importo €.

### AppointmentsByStatusChartWidget (Bar)

Barre raggruppate per slot temporale, una serie per stato (`pending`, `confirmed`, `completed`, `cancelled`). Mostra il trend dei cancellati vs confermati.

### ServiceBreakdownChartWidget (Bar orizzontale)

`COUNT(appointments)` raggruppati per `service_id`, join services, ordinati decrescente. Snapshot statico del periodo, non temporale.

---

## Staff Table

`StaffPerformanceWidget` — widget con Blade inline (no Filament Table Builder).

Colonne: Staff | Appuntamenti | Incasso generato | Tasso cancellazione | Servizio top

Query: join `appointments` → `payments` → `services`, filtrato per range date, raggruppato per `staff_id`. Solo staff con almeno 1 appuntamento nel periodo. Ordinato per incasso generato decrescente.

---

## Edge Cases

- **Nessun dato nel periodo:** card mostrano `€ 0` / `0`, grafici linea piatta, tabella staff vuota con empty state.
- **Staff senza appuntamenti nel periodo:** escluso dalla tabella.
- **Appuntamento senza pagamento:** escluso dal calcolo incassi (query filtra `payment_amount IS NOT NULL AND payments.status = 'completed'`).
- **Divisione per zero** (incasso medio): restituisce `0` se non ci sono pagamenti completati.

---

## Testing

Un test feature `ReportPageTest`:

- La pagina restituisce 403 per utenti staff e customer
- La pagina restituisce 200 per admin
- Con dati seedati nel range: incasso totale, n° appuntamenti, clienti unici corrispondono ai valori attesi
- Appuntamenti fuori range non appaiono nei totali
- Il filtro "Oggi" imposta correttamente dateFrom e dateTo a oggi
