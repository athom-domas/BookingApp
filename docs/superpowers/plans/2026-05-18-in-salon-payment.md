# In-Salon Payment Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Permettere a admin/staff di registrare pagamenti in contanti o POS dal pannello Filament, senza Stripe e senza notificare il cliente.

**Architecture:** Si aggiunge la colonna `payment_method` (`stripe`/`cash`/`pos`) alla tabella `payments`. Un nuovo metodo `recordInPersonPayment` nel `PaymentService` crea il pagamento e conferma l'appuntamento. Il metodo privato `markPaymentCompleted` viene aggiornato per non dispatchare `SendAppointmentConfirmation` per pagamenti non-Stripe. Una nuova action sulla riga di `AppointmentResource` espone la funzionalità nel pannello Filament.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest, MySQL 8

---

## File Map

| File | Azione |
|------|--------|
| `database/migrations/2026_05_18_100000_add_payment_method_to_payments_table.php` | Crea |
| `app/Models/Payment.php` | Modifica — aggiunge `payment_method` a `#[Fillable]` |
| `database/factories/PaymentFactory.php` | Modifica — aggiunge `payment_method` default |
| `app/Services/PaymentService.php` | Modifica — aggiunge `recordInPersonPayment`, aggiorna `markPaymentCompleted` |
| `app/Filament/Resources/AppointmentResource.php` | Modifica — aggiunge action "Registra pagamento" |
| `app/Filament/Resources/PaymentResource.php` | Modifica — aggiunge colonna e filtro `payment_method` |
| `tests/Feature/Services/PaymentServiceTest.php` | Modifica — aggiunge test per `recordInPersonPayment` |

---

## Task 1: Migrazione e aggiornamento modello

**Files:**
- Create: `database/migrations/2026_05_18_100000_add_payment_method_to_payments_table.php`
- Modify: `app/Models/Payment.php`
- Modify: `database/factories/PaymentFactory.php`

- [ ] **Step 1: Crea la migrazione**

Crea il file `database/migrations/2026_05_18_100000_add_payment_method_to_payments_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('payment_method', ['stripe', 'cash', 'pos'])
                ->default('stripe')
                ->after('status');
        });

        DB::table('payments')->update(['payment_method' => 'stripe']);
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_method');
        });
    }
};
```

- [ ] **Step 2: Esegui la migrazione**

```bash
docker-compose run --rm app php artisan migrate
```

Expected: `Migrating: 2026_05_18_100000_add_payment_method_to_payments_table` seguito da `Migrated`.

- [ ] **Step 3: Aggiorna il model `Payment`**

In `app/Models/Payment.php`, riga 11, sostituisci:

```php
#[Fillable(['appointment_id', 'user_id', 'amount', 'status', 'stripe_transaction_id', 'stripe_response'])]
```

con:

```php
#[Fillable(['appointment_id', 'user_id', 'amount', 'status', 'payment_method', 'stripe_transaction_id', 'stripe_response'])]
```

- [ ] **Step 4: Aggiorna la factory**

In `database/factories/PaymentFactory.php`, nel metodo `definition()`, aggiungi `'payment_method' => 'stripe'` prima di `'stripe_transaction_id'`:

```php
public function definition(): array
{
    return [
        'appointment_id' => Appointment::factory(),
        'user_id' => User::factory(),
        'amount' => fake()->randomFloat(2, 10, 500),
        'status' => 'pending',
        'payment_method' => 'stripe',
        'stripe_transaction_id' => null,
        'stripe_response' => null,
    ];
}
```

- [ ] **Step 5: Verifica che la test suite esistente passi ancora**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Expected: tutti i test green.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_05_18_100000_add_payment_method_to_payments_table.php \
        app/Models/Payment.php \
        database/factories/PaymentFactory.php
git commit -m "feat: add payment_method column to payments table"
```

---

## Task 2: `PaymentService` — `recordInPersonPayment`

**Files:**
- Modify: `app/Services/PaymentService.php`
- Test: `tests/Feature/Services/PaymentServiceTest.php`

- [ ] **Step 1: Scrivi i test failing**

In fondo a `tests/Feature/Services/PaymentServiceTest.php`, aggiungi:

```php
it('recordInPersonPayment creates a completed cash payment', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending', 'final_price' => 50.00]);
    $mockStripe = Mockery::mock(StripeClient::class);

    $payment = ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    expect($payment->status)->toBe('completed');
    expect($payment->payment_method)->toBe('cash');
    expect((float) $payment->amount)->toBe(50.00);
    expect($payment->stripe_transaction_id)->toBeNull();
    expect($payment->appointment_id)->toBe($appointment->id);
});

it('recordInPersonPayment creates a completed pos payment', function () {
    $appointment = Appointment::factory()->create();
    $mockStripe = Mockery::mock(StripeClient::class);

    $payment = ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'pos', 30.00);

    expect($payment->status)->toBe('completed');
    expect($payment->payment_method)->toBe('pos');
});

it('recordInPersonPayment sets appointment status to confirmed', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    expect($appointment->fresh()->status)->toBe('confirmed');
});

it('recordInPersonPayment does not dispatch SendAppointmentConfirmation', function () {
    $appointment = Appointment::factory()->create(['status' => 'pending']);
    $mockStripe = Mockery::mock(StripeClient::class);

    ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00);

    Queue::assertNotPushed(SendAppointmentConfirmation::class);
});

it('recordInPersonPayment throws BookingException if a completed payment already exists', function () {
    $appointment = Appointment::factory()->create();
    Payment::factory()->create([
        'appointment_id' => $appointment->id,
        'status' => 'completed',
    ]);
    $mockStripe = Mockery::mock(StripeClient::class);

    expect(fn () => ($this->makePaymentService)($mockStripe)->recordInPersonPayment($appointment->id, 'cash', 50.00))
        ->toThrow(BookingException::class);
});
```

- [ ] **Step 2: Esegui i test per verificare che falliscano**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php --filter "recordInPersonPayment"
```

Expected: FAIL con `Call to undefined method ... recordInPersonPayment`.

- [ ] **Step 3: Aggiorna `markPaymentCompleted` in `PaymentService`**

In `app/Services/PaymentService.php`, nel metodo privato `markPaymentCompleted` (riga 103), sostituisci:

```php
if (! $alreadyCompleted) {
    SendAppointmentConfirmation::dispatch($appointment->fresh());
}
```

con:

```php
if (! $alreadyCompleted && $payment->payment_method === 'stripe') {
    SendAppointmentConfirmation::dispatch($appointment->fresh());
}
```

- [ ] **Step 4: Aggiungi il metodo `recordInPersonPayment` a `PaymentService`**

In `app/Services/PaymentService.php`, aggiungi questo metodo pubblico dopo `refundPayment` (prima di `private function markPaymentCompleted`):

```php
public function recordInPersonPayment(int $appointmentId, string $method, float $amount): Payment
{
    $appointment = Appointment::findOrFail($appointmentId);

    $existing = $appointment->payment;
    if ($existing && $existing->status === 'completed') {
        throw new BookingException('Esiste già un pagamento completato per questo appuntamento.');
    }

    $payment = Payment::create([
        'appointment_id' => $appointmentId,
        'user_id'        => $appointment->user_id,
        'amount'         => $amount,
        'status'         => 'pending',
        'payment_method' => $method,
    ]);

    $this->markPaymentCompleted($payment);

    return $payment->fresh();
}
```

- [ ] **Step 5: Esegui tutti i test del PaymentService**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Services/PaymentServiceTest.php
```

Expected: tutti i test green, inclusi quelli esistenti (la modifica a `markPaymentCompleted` non rompe i test Stripe perché la factory usa `payment_method = 'stripe'` di default).

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php tests/Feature/Services/PaymentServiceTest.php
git commit -m "feat: add recordInPersonPayment to PaymentService"
```

---

## Task 3: Action Filament su `AppointmentResource`

**Files:**
- Modify: `app/Filament/Resources/AppointmentResource.php`

- [ ] **Step 1: Aggiungi gli import necessari**

In `app/Filament/Resources/AppointmentResource.php`, aggiungi questi import dopo quelli esistenti:

```php
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
```

- [ ] **Step 2: Aggiungi la action nella tabella**

Nel metodo `table()`, nell'array `->actions([...])`, aggiungi la nuova action prima di `EditAction::make()`:

```php
->actions([
    Action::make('register_payment')
        ->label('Registra pagamento')
        ->icon('heroicon-o-banknotes')
        ->color('success')
        ->form([
            Select::make('method')
                ->label('Metodo di pagamento')
                ->options([
                    'cash' => 'Contanti',
                    'pos'  => 'POS (carta)',
                ])
                ->required(),
            TextInput::make('amount')
                ->label('Importo (€)')
                ->numeric()
                ->minValue(0.01)
                ->required(),
        ])
        ->fillForm(fn (Appointment $record): array => [
            'amount' => $record->final_price,
        ])
        ->action(function (Appointment $record, array $data): void {
            app(PaymentService::class)->recordInPersonPayment(
                $record->id,
                $data['method'],
                (float) $data['amount']
            );
        })
        ->successNotificationTitle('Pagamento registrato con successo')
        ->visible(fn (Appointment $record): bool => ! $record->payment || $record->payment->status !== 'completed'),
    EditAction::make(),
    DeleteAction::make(),
])
```

- [ ] **Step 3: Verifica manuale nel browser**

Avvia i servizi se non già attivi:
```bash
docker-compose up -d
```

Apri il pannello Filament all'indirizzo `http://localhost/admin/appointments`. Verifica che:
- Il pulsante "Registra pagamento" appaia sugli appuntamenti senza pagamento completato
- Cliccando si apra un modal con i campi "Metodo di pagamento" e "Importo"
- L'importo sia pre-compilato col valore di `final_price`
- Dopo la conferma, il pagamento venga registrato e il pulsante scompaia dalla riga

- [ ] **Step 4: Verifica la suite di test**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: nessuna regressione.

- [ ] **Step 5: Commit**

```bash
git add app/Filament/Resources/AppointmentResource.php
git commit -m "feat: add register in-person payment action to AppointmentResource"
```

---

## Task 4: `PaymentResource` — colonna e filtro `payment_method`

**Files:**
- Modify: `app/Filament/Resources/PaymentResource.php`

- [ ] **Step 1: Aggiungi la colonna `payment_method` nella tabella**

In `app/Filament/Resources/PaymentResource.php`, nel metodo `table()`, aggiungi la colonna `payment_method` dopo la colonna `status`:

```php
TextColumn::make('payment_method')
    ->label('Metodo')
    ->badge()
    ->formatStateUsing(fn (string $state): string => match ($state) {
        'stripe' => 'Stripe',
        'cash'   => 'Contanti',
        'pos'    => 'POS',
        default  => $state,
    })
    ->color(fn (string $state): string => match ($state) {
        'stripe' => 'info',
        'cash'   => 'success',
        'pos'    => 'warning',
        default  => 'secondary',
    }),
```

- [ ] **Step 2: Aggiungi il filtro `payment_method`**

Nell'array `->filters([...])`, aggiungi dopo il filtro `status`:

```php
SelectFilter::make('payment_method')
    ->label('Metodo')
    ->options([
        'stripe' => 'Stripe',
        'cash'   => 'Contanti',
        'pos'    => 'POS',
    ]),
```

- [ ] **Step 3: Aggiorna il filtro `status` esistente per includere `cancelled`**

Nota: il filtro `status` esistente manca dell'opzione `'cancelled'`. Aggiungi:

```php
SelectFilter::make('status')
    ->label('Stato')
    ->options([
        'pending'   => 'In attesa',
        'completed' => 'Completato',
        'refunded'  => 'Rimborsato',
        'failed'    => 'Fallito',
        'cancelled' => 'Annullato',
    ]),
```

- [ ] **Step 4: Verifica manuale nel browser**

Apri `http://localhost/admin/payments`. Verifica che la colonna "Metodo" appaia con il badge corretto e che il filtro funzioni.

- [ ] **Step 5: Esegui la suite completa**

```bash
docker-compose run --rm app ./vendor/bin/pest
```

Expected: tutti i test green.

- [ ] **Step 6: Commit**

```bash
git add app/Filament/Resources/PaymentResource.php
git commit -m "feat: show payment_method column and filter in PaymentResource"
```
