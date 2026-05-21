# Booking App

Gestionale prenotazioni in Laravel con portale clienti, pannello admin Filament, pagamenti Stripe, notifiche e sincronizzazione calendario.

## Stack

- Laravel 13, PHP 8.4
- Filament 4 per admin su `/admin`
- MySQL 8, Redis 7, Mailpit
- Stripe, Twilio, Google Calendar
- Pest per test feature/unit

## Setup locale

Il progetto e pensato per girare in Docker.

```bash
cp .env.example .env
docker-compose up -d
docker-compose run --rm app php artisan key:generate
docker-compose run --rm app php artisan migrate --seed
```

Servizi principali:

- App: `http://localhost:8000`
- Admin Filament: `http://localhost:8000/admin`
- Mailpit: `http://localhost:8025`
- phpMyAdmin: `http://localhost:8080`

Utenti seed:

- `admin@test.com` / `password`
- `staff@test.com` / `password`
- `customer@test.com` / `password`

## Variabili integrazioni

Impostare in `.env`:

```env
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_SECRET_KEY=sk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...

TWILIO_SID=
TWILIO_TOKEN=
TWILIO_FROM=

GOOGLE_APPLICATION_CREDENTIALS=/app/config/google-credentials.json
GOOGLE_CALENDAR_ID=
```

Webhook Stripe locale:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
```

## Comandi utili

```bash
docker-compose run --rm app ./vendor/bin/pest
docker-compose run --rm app php artisan migrate:fresh --seed
docker-compose run --rm app php artisan schedule:run
docker-compose run --rm app php artisan queue:listen --tries=1
npm run build
```

## Funzionalita

- Portale clienti Blade: registrazione, login, prenotazione, pagamento Stripe, storico appuntamenti.
- API Sanctum per servizi, appuntamenti e pagamenti.
- Admin Filament per servizi, disponibilita, slot, prenotazioni e pagamenti.
- Job per reminder, conferme, cancellazioni, Google Calendar e generazione slot.
- Test automatici su modelli, servizi, API, job, mail, admin e portale.
