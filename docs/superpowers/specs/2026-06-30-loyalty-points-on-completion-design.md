# Loyalty Points: Trigger su Completamento + Prezzo Scontato su Appointment

**Data:** 2026-06-30

## Contesto

Il sistema fedeltà esiste già (LoyaltyAccount, LoyaltyTransaction, LoyaltyService, toggle UI nel form di completamento). Attualmente i punti vengono accreditati quando lo status dell'appuntamento diventa `confirmed`. Il pagamento online via Stripe ha un listener separato (`CreditLoyaltyPoints` su `PaymentCompleted`).

## Obiettivo

1. Accreditare i punti fedeltà solo al completamento dell'appuntamento (`completed`), non alla conferma.
2. Salvare il prezzo scontato anche sull'`Appointment` (oltre che sul `Payment` dove già esiste).

## Flussi di pagamento

- **Online (Stripe):** il cliente paga → evento `PaymentCompleted` → `CreditLoyaltyPoints` listener → punti accreditati. Al completamento dell'appuntamento, `LoyaltyService::accrue()` viene chiamato ma il guard di idempotenza lo rende un no-op.
- **In salone:** l'appuntamento viene marcato `completed` → `AppointmentObserver::updated()` → `accrue()` → punti accreditati. L'admin può attivare il toggle sconto fedeltà nel form: i punti vengono scalati (`redeem()`), il prezzo scontato viene salvato su Payment e (con questa modifica) su Appointment.

## Modifiche

### 1. AppointmentObserver

**Rimuovere** `accrue()` dal metodo `created()` (blocco che scatta quando status iniziale è `confirmed`).

**Nel metodo `updated()`**, rimuovere `accrue()` dal ramo `confirmed` e aggiungerlo al ramo `completed`:

```php
// Prima
if ($appointment->status === 'confirmed') {
    $this->accrue($appointment);
} elseif ($appointment->status === 'cancelled') {
    $this->reverse($appointment);
} elseif ($appointment->status === 'completed') {
    $this->scheduleReviewRequest($appointment);
    $this->scheduleFollowUpReminder($appointment);
}

// Dopo
if ($appointment->status === 'cancelled') {
    $this->reverse($appointment);
} elseif ($appointment->status === 'completed') {
    $this->accrue($appointment);
    $this->scheduleReviewRequest($appointment);
    $this->scheduleFollowUpReminder($appointment);
}
```

Il `reverse()` su `cancelled` rimane invariato (safety net: no-op se non ci sono earn da stornare).

### 2. Migration: `loyalty_discounted_price` su `appointments`

Nuova colonna nullable `decimal(8,2)` sulla tabella `appointments`.

```php
$table->decimal('loyalty_discounted_price', 8, 2)->nullable()->after('final_price');
```

### 3. Appointment model

Aggiungere `loyalty_discounted_price` al fillable e ai casts (`decimal:2`).

### 4. Salvataggio prezzo scontato — due punti

**`EditAppointment::beforeSave()`** — dopo `recordInPersonPayment()`, se sconto applicato:

```php
$this->record->update(['loyalty_discounted_price' => $amount]);
```

**`register_payment` action in `AppointmentResource`** — dopo `recordInPersonPayment()`, se sconto applicato:

```php
$record->update(['loyalty_discounted_price' => $amount]);
```

## Invarianti

- `CreditLoyaltyPoints` listener (PaymentCompleted) resta invariato.
- `ReverseLoyaltyPoints` listener (PaymentRefunded) resta invariato.
- `LoyaltyService::accrue()` ha già il guard di idempotenza (una sola transazione `earn` per appuntamento).
- Il toggle UI nel form esiste già e mostra/nasconde correttamente in base a punti cliente, loyalty enabled, status completing, pagamento non ancora completato.
- `loyalty_discounted_price` è null se nessuno sconto è stato applicato; `final_price` contiene sempre il prezzo originale.

## File coinvolti

| File | Azione |
|------|--------|
| `app/Observers/AppointmentObserver.php` | Sposta `accrue()` da `confirmed` a `completed` |
| `database/migrations/YYYY_MM_DD_xxxxxx_add_loyalty_discounted_price_to_appointments.php` | Nuova colonna |
| `app/Models/Appointment.php` | Fillable + cast |
| `app/Filament/Resources/AppointmentResource/Pages/EditAppointment.php` | Salva `loyalty_discounted_price` su appointment |
| `app/Filament/Resources/AppointmentResource.php` | Salva `loyalty_discounted_price` su appointment nell'action `register_payment` |

## Test da aggiornare/aggiungere

- Verifica che `accrue()` NON venga chiamato quando status → `confirmed`
- Verifica che `accrue()` venga chiamato quando status → `completed`
- Verifica che `loyalty_discounted_price` venga salvato sull'appointment quando lo sconto è applicato
- Verifica che `loyalty_discounted_price` sia null quando lo sconto non è applicato
