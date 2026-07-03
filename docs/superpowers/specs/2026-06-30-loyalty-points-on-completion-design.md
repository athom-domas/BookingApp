# Loyalty Points: Trigger su Completamento + Prezzo Scontato su Appointment

**Data:** 2026-06-30

## Principio guida

I punti fedeltà si guadagnano quando il servizio è stato **effettivamente erogato** (appointment `completed`). Il pagamento Stripe conferma solo l'incasso e non ha nessun ruolo nel sistema fedeltà. Questo evita punti accreditati per appuntamenti pagati ma poi annullati, spostati, rimborsati o non effettuati.

## Obiettivo

1. Accreditare i punti fedeltà solo quando l'appuntamento passa a `completed`.
2. Rimuovere il coinvolgimento del pagamento (Stripe) dall'accredito e storno punti.
3. Salvare il prezzo scontato anche sull'`Appointment` (oltre che sul `Payment` dove già esiste).

## Flussi loyalty

- **Online (Stripe):** il cliente paga online → nessun punto. L'appuntamento viene marcato `completed` dall'admin → `accrue()` → punti accreditati.
- **In salone:** l'appuntamento viene marcato `completed` → `accrue()` → punti accreditati. L'admin può attivare il toggle sconto fedeltà nel form: i punti vengono scalati (`redeem()`), il prezzo scontato viene salvato su Payment e su Appointment.
- **Cancellazione:** `reverse()` sull'observer per `cancelled` — no-op se nessun earn esiste.
- **Rimborso Stripe:** nessun effetto sulla loyalty (il servizio era stato erogato o non era mai stato erogato indipendentemente dal pagamento).

## Modifiche

### 1. AppointmentObserver

**Rimuovere** `accrue()` dal metodo `created()` (blocco che scattava quando status iniziale è `confirmed`).

**Nel metodo `updated()`**, rimuovere `accrue()` dal ramo `confirmed` e aggiungerlo al ramo `completed`:

```php
// Dopo
if ($appointment->status === 'cancelled') {
    $this->reverse($appointment);
} elseif ($appointment->status === 'completed') {
    $this->accrue($appointment);
    $this->scheduleReviewRequest($appointment);
    $this->scheduleFollowUpReminder($appointment);
}
```

### 2. Rimuovere i listener legati al pagamento

- **Eliminare** `app/Listeners/CreditLoyaltyPoints.php` — non più necessario, il pagamento non accredita punti.
- **Eliminare** `app/Listeners/ReverseLoyaltyPoints.php` — non più necessario, il rimborso non storna punti; la cancellazione è gestita dall'observer.

### 3. Migration: `loyalty_discounted_price` su `appointments`

Nuova colonna nullable `decimal(8,2)` sulla tabella `appointments`.

```php
$table->decimal('loyalty_discounted_price', 8, 2)->nullable()->after('final_price');
```

### 4. Appointment model

Aggiungere `loyalty_discounted_price` al fillable e ai casts (`decimal:2`).

### 5. Salvataggio prezzo scontato — due punti

**`EditAppointment::beforeSave()`** — dopo `recordInPersonPayment()`, se sconto applicato:

```php
$this->record->update(['loyalty_discounted_price' => $amount]);
```

**`register_payment` action in `AppointmentResource`** — dopo `recordInPersonPayment()`, se sconto applicato:

```php
$record->update(['loyalty_discounted_price' => $amount]);
```

## Invarianti

- `LoyaltyService::accrue()` ha già il guard di idempotenza (una sola transazione `earn` per appuntamento).
- Il toggle UI nel form esiste già e mostra/nasconde correttamente in base a punti cliente, loyalty enabled, status completing, pagamento non ancora completato.
- `loyalty_discounted_price` è null se nessuno sconto è stato applicato; `final_price` contiene sempre il prezzo originale.
- `reverse()` su `cancelled` rimane: safety net, no-op se non ci sono earn da stornare.

## File coinvolti

| File | Azione |
|------|--------|
| `app/Observers/AppointmentObserver.php` | Sposta `accrue()` da `confirmed` a `completed`; rimuovi da `created()` |
| `app/Listeners/CreditLoyaltyPoints.php` | **Eliminare** |
| `app/Listeners/ReverseLoyaltyPoints.php` | **Eliminare** |
| `database/migrations/YYYY_MM_DD_xxxxxx_add_loyalty_discounted_price_to_appointments.php` | Nuova colonna |
| `app/Models/Appointment.php` | Fillable + cast |
| `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php` | Salva `loyalty_discounted_price` su appointment |
| `app/Filament/Resources/AppointmentResource.php` | Salva `loyalty_discounted_price` su appointment nell'action `register_payment` |

## Test da aggiornare/aggiungere

- Verifica che `accrue()` NON venga chiamato quando status → `confirmed`
- Verifica che `accrue()` venga chiamato quando status → `completed`
- Verifica che `PaymentCompleted` non triggeri più `accrue()` (listener rimosso)
- Verifica che `loyalty_discounted_price` venga salvato sull'appointment quando lo sconto è applicato
- Verifica che `loyalty_discounted_price` sia null quando lo sconto non è applicato
