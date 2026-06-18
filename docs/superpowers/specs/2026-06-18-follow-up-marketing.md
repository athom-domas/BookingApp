# Promemoria di Follow-up — Design Spec

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Inviare un promemoria automatico ai clienti che hanno già completato almeno un appuntamento, se dopo N giorni non hanno ancora una prenotazione futura. Il cliente può disabilitarlo; l'admin lo attiva globalmente.

**Architecture:** Nuovo modello `FollowUpReminder` / tabella `follow_up_reminders` separata da `appointment_reminders` (incompatibile: `type` ha semantica diversa — canale vs. scopo). Il trigger è `AppointmentObserver::updated()` su status `completed`. Un job schedulato invia i reminder con re-check eligibilità completo prima dell'invio.

**Tech Stack:** Laravel 13, Filament 4, MySQL 8. Riusa: `BelongsToBusiness` trait, canale email esistente, `UserPreference`, `SystemSetting`, `routes/console.php` scheduler.

**Non in scope:** re-engagement di utenti che non hanno mai prenotato; WhatsApp/SMS (solo email, canale più conservativo per questo tipo di messaggio); sistema coupon; "marketing consent" generico.

---

## 1. Tabella `follow_up_reminders`

| Colonna | Tipo | Note |
|---|---|---|
| `id` | bigint PK | |
| `business_id` | FK businesses, cascade delete | via BelongsToBusiness |
| `user_id` | FK users, cascade delete | cliente destinatario |
| `appointment_id` | FK appointments nullable, nullOnDelete | appuntamento che ha triggerato il reminder |
| `type` | enum `rebooking` | estendibile in futuro |
| `channel` | string default `email` | canale di invio (audit + estensibilità) |
| `scheduled_for` | datetime | quando inviare |
| `sent_at` | datetime nullable | |
| `status` | enum `pending\|processing\|sent\|failed\|skipped` | `processing` = claimed da un worker |
| `processing_at` | datetime nullable | timestamp del claim; per recupero stale |
| `skipped_reason` | string nullable | motivo skip (es. "user_has_future_appointment") |
| `error_message` | text nullable | max 1000 char |
| `delay_days` | integer | snapshot dei giorni al momento della creazione |
| `timestamps` | | |

**Index:**
- `(status, scheduled_for)`
- `(business_id, status, scheduled_for)` — query scheduler
- `(business_id, user_id, type, status)` — check duplicati
- `(business_id, user_id, type, created_at)` — audit

**Nota:** `appointment_id` è `nullOnDelete` (non `cascadeOnDelete`) — se l'appuntamento viene eliminato, il reminder rimane tracciato con `appointment_id = null`.

---

## 2. Modello `FollowUpReminder`

```php
namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @use HasFactory<\Database\Factories\FollowUpReminderFactory> */
class FollowUpReminder extends Model
{
    use BelongsToBusiness, HasFactory;

    #[Fillable(['business_id', 'user_id', 'appointment_id', 'type', 'channel', 'delay_days', 'scheduled_for', 'sent_at', 'status', 'processing_at', 'skipped_reason', 'error_message'])]

    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'sent_at'       => 'datetime',
            'processing_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('scheduled_for', '<=', now());
    }

    public function scopeProcessing(Builder $query): Builder
    {
        return $query->where('status', 'processing');
    }

    public function scopeStale(Builder $query): Builder
    {
        return $query->where('status', 'processing')
                     ->where('processing_at', '<=', now()->subMinutes(60));
    }
}
```

---

## 3. Preferenza utente

**Migrazione su `user_preferences`:** aggiunge colonna:
- `follow_up_reminders_enabled` boolean, default `true`, `NOT NULL`

**Portale clienti** — sezione "Comunicazioni" (nuova o esistente):

> **Toggle:** "Ricevi promemoria per prenotare un nuovo appuntamento"
> **Help text:** "Ti invieremo un promemoria se è passato un po' dal tuo ultimo appuntamento e non hai ancora una nuova prenotazione."

Il cliente può disattivarlo in qualsiasi momento. Il job ri-verifica la preferenza prima di inviare.

---

## 4. Impostazioni admin

**Migrazione su `system_settings`:** aggiunge 2 colonne:
- `follow_up_reminders_enabled` boolean, default `false`
- `follow_up_reminder_days` integer, default `30`

**Metodi helper su `SystemSetting`:**
```php
public function isFollowUpRemindersEnabled(): bool
{
    return (bool) $this->follow_up_reminders_enabled;
}

public function getFollowUpReminderDays(): int
{
    return (int) ($this->follow_up_reminder_days ?? 30);
}
```

`SystemSetting` è già multi-tenant (scoped per `business_id`); i metodi si chiamano su un'istanza, non staticamente.

**Filament — sezione "Promemoria di follow-up":**
- Toggle `follow_up_reminders_enabled` — label: "Abilita promemoria di follow-up"
- Campo numerico `follow_up_reminder_days` — label: "Giorni dopo l'ultimo appuntamento", min 7, max 365, visibile solo se toggle on

---

## 5. Trigger: `AppointmentObserver::updated()`

Aggiungere nella logica esistente che gestisce il cambio di status a `completed`:

```
1. Carica SystemSetting per $appointment->business_id
2. isFollowUpRemindersEnabled() → false? → return
3. $appointment->user_id null? → return
4. $appointment->user->preferences null
   OR follow_up_reminders_enabled = false? → return
5. L'utente ha già un appuntamento futuro per questo business
   (status IN pending|confirmed, scheduled_date > today)? → return
6. Esiste già un FollowUpReminder (type=rebooking, status IN pending|processing|sent)
   per questo appointment_id? → return (stesso trigger)
7. Esiste già un FollowUpReminder (type=rebooking, status IN pending|processing)
   per questo business_id + user_id? → return (evita doppi attivi)
8. Crea FollowUpReminder:
   - business_id = $appointment->business_id
   - user_id = $appointment->user_id
   - appointment_id = $appointment->id
   - type = 'rebooking'
   - delay_days = $settings->getFollowUpReminderDays()  ← snapshot
   - scheduled_for = now()->addDays($settings->getFollowUpReminderDays())
   - status = 'pending'
```

---

## 6. Job `SendFollowUpReminder`

Il job riceve `$reminderId` (int), non il model, per evitare modelli stale serializzati.

**Flusso:**

```
1. Claim atomico — previene doppio invio da worker concorrenti:
   $claimed = FollowUpReminder::whereKey($reminderId)
       ->where('status', 'pending')
       ->where('scheduled_for', '<=', now())
       ->update(['status' => 'processing', 'processing_at' => now()]);
   if (! $claimed) → return (già claimed o non ancora il momento)

2. Ricarica $reminder dal DB.

3. Carica SystemSetting per $reminder->business_id.
   isFollowUpRemindersEnabled() → false?
   → skipped, skipped_reason = 'feature_disabled'

4. $reminder->user->preferences null
   OR follow_up_reminders_enabled = false?
   → skipped, skipped_reason = 'user_disabled'

5. L'utente ha un appuntamento futuro per questo business
   (status IN pending|confirmed, scheduled_date > now())?
   → skipped, skipped_reason = 'user_has_future_appointment'

6. Carica l'ultimo appuntamento completed dell'utente per questo business.
   Se esiste UN appuntamento più recente rispetto a $reminder->appointment_id
   AND la sua scheduled_date > now()->subDays($reminder->delay_days)
   → skipped, skipped_reason = 'recent_appointment_completed'
   (= cliente è tornato nel frattempo; si usa delay_days snapshot, non il valore admin corrente)

7. Invia email via FollowUpReminderMail.
   Success → status = 'sent', sent_at = now(), channel = 'email'
   Exception → status = 'failed',
               error_message = Str::limit($e->getMessage(), 1000)
```

**Nota su step 6:** la condizione corretta per lo skip è che l'ultimo appuntamento completed sia **più recente** del trigger originale **e** più recente della finestra N giorni. Se l'utente ha solo vecchi appuntamenti (tutti > N giorni fa), è correttamente eligibile.

**Canale:** solo email. Non WhatsApp/SMS — riservati a reminder pre-appuntamento dove l'opt-in è già gestito.

---

## 7. Mail `FollowUpReminderMail`

Testo neutro, non promozionale:

**Subject:** "Vuoi prenotare un nuovo appuntamento?"

**Body:**
> "È passato un po' dal tuo ultimo appuntamento. Se vuoi programmare una nuova visita, puoi prenotare dal portale."
> [Link al portale di prenotazione]
> "Non vuoi più ricevere questi promemoria? [Disattivali qui] senza bisogno di login."

Il link di unsubscribe è una URL firmata con scadenza lunga (es. 1 anno):

```
GET /follow-up-reminders/unsubscribe/{user}?signature=...
```

Comportamento: imposta `user_preferences.follow_up_reminders_enabled = false` e mostra una pagina di conferma ("Promemoria disattivati."). Nessun login richiesto — segue lo stesso pattern delle URL firmate già usate per conferma/disdici appuntamento.

**Rationale compliance:** per email a clienti esistenti (art. 130 comma 4 Codice Privacy), il diritto di opposizione deve essere esercitabile in modo agevole. Un link diretto senza login è il minimo pratico.

---

## 8. Scheduler

In `routes/console.php`, accanto alla logica esistente di `AppointmentReminder`:

```php
Schedule::call(function () {
    FollowUpReminder::pending()
        ->orderBy('id')
        ->chunkById(100, function ($reminders) {
            foreach ($reminders as $reminder) {
                SendFollowUpReminder::dispatch($reminder->id);
            }
        });
})->everyFiveMinutes();
```

Segue la stessa frequenza (ogni 5 minuti) del reminder esistente. Non carica tutti i record in memoria.

**Stale recovery** — schedulato ogni ora, riporta `processing` in `pending` se `processing_at` è più vecchio di 60 minuti (worker crashato):

```php
Schedule::call(function () {
    FollowUpReminder::stale()->update(['status' => 'pending', 'processing_at' => null]);
})->hourly();
```

**`delay_days` — snapshot behavior:** il numero di giorni viene salvato al momento della creazione. Se l'admin cambia il valore nelle impostazioni, i reminder già in coda non vengono riprocessati. L'admin può comunque disabilitare tutta la feature per bloccare invii imminenti.

---

## 9. Test

| Scenario | Tipo |
|---|---|
| Nessun reminder creato se feature disabilitata nelle impostazioni | Feature |
| Reminder creato quando appointment diventa completed e feature abilitata | Feature |
| Nessun reminder se l'utente ha `follow_up_reminders_enabled = false` | Feature |
| Nessun reminder se l'utente ha già un appuntamento futuro | Feature |
| Nessun reminder duplicato per lo stesso appointment | Feature |
| Nessun reminder duplicato pending per lo stesso user/business | Feature |
| Job skippato se utente disabilita after creazione reminder | Feature |
| Job skippato se admin disabilita feature after creazione reminder | Feature |
| Job skippato se utente prenota un appuntamento futuro before invio | Feature |
| Job skippato se utente ha completato un appuntamento più recente (latest non abbastanza vecchio) | Feature |
| Job marca `sent` su invio email riuscito | Feature |
| Job marca `failed` su eccezione del provider | Feature |
| Claim atomico — secondo job sullo stesso reminder non invia (concurrency) | Feature |
| URL firmata di unsubscribe imposta `follow_up_reminders_enabled = false` | Feature |
| URL firmata di unsubscribe con firma invalida ritorna 403 | Feature |

---

## 10. Cosa NON è in scope

- Re-engagement di utenti che non hanno mai prenotato (spec separata)
- Auguri compleanno con coupon (richiede sistema coupon — spec separata)
- WhatsApp/SMS per follow-up
- Template personalizzabili dall'admin
- Report/statistiche sui follow-up inviati
- `MarketingMessage`, `marketing_opt_in`, consenso marketing generico
