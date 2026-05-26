# Booking App

Gestionale prenotazioni in Laravel con portale clienti, pannello admin Filament, pagamenti Stripe, notifiche multicanale e sincronizzazione Google Calendar.

## Stack

- Laravel 13, PHP 8.4
- Filament 4 — pannello admin su `/admin`
- MySQL 8, Redis 7
- Stripe — pagamenti e rimborsi automatici
- Twilio — notifiche SMS e WhatsApp
- Google Calendar — sincronizzazione appuntamenti
- Pest — test feature/unit
- Mailpit — mail catcher in locale

## Setup locale

Il progetto gira interamente in Docker.

```bash
cp .env.example .env
# Imposta APP_KEY e le variabili integrazioni in .env
docker-compose up -d
docker-compose run --rm app php artisan key:generate
docker-compose run --rm app php artisan migrate --seed
```

Servizi disponibili dopo il boot:

| Servizio     | URL                          |
|--------------|------------------------------|
| App          | http://localhost:8000        |
| Admin        | http://localhost:8000/admin  |
| Portale      | http://localhost:8000/portal |
| Mailpit      | http://localhost:8025        |
| phpMyAdmin   | http://localhost:8080        |

Credenziali seed:

| Ruolo    | Email               | Password  |
|----------|---------------------|-----------|
| Admin    | admin@test.com      | password  |
| Staff    | staff@test.com      | password  |
| Cliente  | customer@test.com   | password  |

## Variabili d'ambiente

```env
# Stripe
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

# Twilio (SMS / WhatsApp)
TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

# Google Calendar
GOOGLE_APPLICATION_CREDENTIALS=/app/config/google-credentials.json
GOOGLE_CALENDAR_ID=
```

Per testare i webhook Stripe in locale:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

## Comandi utili

```bash
# Test
docker-compose run --rm app php -d memory_limit=256M ./vendor/bin/pest

# Database
docker-compose run --rm app php artisan migrate:fresh --seed

# Scheduler e queue
docker-compose run --rm app php artisan schedule:run
docker-compose run --rm app php artisan queue:listen --tries=1

# Frontend
npm run build

# Inviare manualmente i reminder di un appuntamento (es. per test)
docker-compose run --rm app php artisan reminder:send {appointment_id}
```

## Funzionalità

### Portale clienti (`/portal`)
- Registrazione, login, gestione profilo
- Prenotazione con selezione servizi, operatore e slot disponibili
- Pagamento online via Stripe
- Storico appuntamenti e cancellazione self-service
- Preferenze notifiche: email, SMS o WhatsApp

### Pannello admin (`/admin`)
- Gestione servizi, staff, disponibilità e slot
- Calendario prenotazioni interattivo (Filament widget)
- Completamento appuntamenti con registrazione pagamento (contanti/POS)
- Rimborso Stripe con un click
- Notifiche email per nuove prenotazioni e cancellazioni

### Notifiche e reminder
- Conferma prenotazione al cliente via email
- Reminder inviato 48 ore prima dell'appuntamento (email, SMS o WhatsApp in base alle preferenze)
- Link firmati per confermare o disdire direttamente dall'email — validi fino a 24 ore prima dell'appuntamento
- Notifica cancellazione al cliente e agli admin, con indicazione del pagamento da rimborsare

### Pagamenti
- Checkout Stripe embedded nel portale clienti
- Rimborso automatico alla cancellazione se pagato con Stripe
- Pagamenti manuali (contanti/POS) registrabili dall'admin al completamento

### API
- Endpoint pubblici per slot disponibili e date prenotabili
- Endpoint autenticati (Sanctum) per gestione appuntamenti

## Sicurezza

- Tutti i link di conferma/cancellazione usano signed URL con binding sull'utente (`uid`)
- `APP_DEBUG=false` obbligatorio in produzione (il `.env.example` è già impostato correttamente)
- Webhook Stripe verificato tramite firma (`STRIPE_WEBHOOK_SECRET`)
- Ruoli: `admin`, `staff`, `customer` via Spatie Permission
