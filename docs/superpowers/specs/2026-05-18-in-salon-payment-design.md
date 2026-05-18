# In-Salon Payment Design

**Date:** 2026-05-18  
**Status:** Approved

## Overview

Aggiungere la possibilità per admin/staff di registrare pagamenti incassati fisicamente in salone (contanti o POS), dal pannello Filament, senza coinvolgere Stripe e senza notificare il cliente.

## Database

Nuova migrazione su `payments`:

- Aggiunge colonna `payment_method` enum: `stripe`, `cash`, `pos` — default `stripe`
- Aggiorna i record esistenti: `UPDATE payments SET payment_method = 'stripe'`
- `stripe_transaction_id` e `stripe_response` restano nullable (null per pagamenti in salone)

Model `Payment`:
- `payment_method` aggiunto a `#[Fillable]`
- `payment_method` aggiunto ai `casts()` (string, nessun cast speciale necessario)

## PaymentService

Nuovo metodo pubblico:

```php
public function recordInPersonPayment(int $appointmentId, string $method, float $amount): Payment
```

Comportamento:
1. `Appointment::findOrFail($appointmentId)`
2. Se esiste già un `Payment` con `status = completed` → lancia `BookingException`
3. Crea `Payment` con `status = completed`, `payment_method = $method`, `amount = $amount`, `stripe_transaction_id = null`
4. Chiama il metodo privato `markPaymentCompleted($payment)`

Modifica a `markPaymentCompleted`:
- Il dispatch di `SendAppointmentConfirmation` avviene **solo se** `$payment->payment_method === 'stripe'`

Parametro `$method` accetta `'cash'` o `'pos'` — la validazione è delegata all'action Filament (select con opzioni fisse).

## Filament — AppointmentResource

Nuova action sulla riga della tabella:

- **Label:** "Registra pagamento"
- **Icona:** `heroicon-o-banknotes`
- **Visibilità:** `!$record->payment || $record->payment->status !== 'completed'`
- **Modal con:**
  - `method` — Select obbligatorio: `cash` → "Contanti", `pos` → "POS (carta)"
  - `amount` — TextInput numerico obbligatorio, pre-compilato con `$record->final_price`
- **Al submit:** chiama `PaymentService::recordInPersonPayment()`, mostra notifica di successo Filament

## Filament — PaymentResource

- Aggiungere colonna `payment_method` nella tabella con label "Metodo" e badge colorato
- Aggiungere opzione `payment_method` nei filtri esistenti

## Scope escluso

- Nessuna notifica al cliente per pagamenti in salone
- Nessuna integrazione con terminale POS fisico
- Nessuna modifica al flusso Stripe esistente
