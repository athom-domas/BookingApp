# Integration Settings Design

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Allow the salon admin to configure Stripe, Twilio, and Google Calendar credentials directly from the Filament admin panel, without needing server/env access.

**Architecture:** A new `IntegrationSetting` singleton model (same pattern as `SystemSetting`) stores all credentials in the DB. Sensitive values use Laravel's `encrypted` cast. Existing services read from `IntegrationSetting` first, falling back to `.env` config if fields are empty. A new Filament page "Integrazioni" exposes all fields.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, MySQL 8, Laravel `encrypted` cast

---

## Database

New `integration_settings` table (singleton, always record ID=1):

| Column | Type | Encrypted | Description |
|--------|------|-----------|-------------|
| `stripe_public_key` | string, nullable | no | Publishable key (`pk_live_...`) |
| `stripe_secret_key` | text, nullable | yes | Secret key (`sk_live_...`) |
| `stripe_webhook_secret` | text, nullable | yes | Webhook signing secret (`whsec_...`) |
| `twilio_sid` | string, nullable | yes | Account SID |
| `twilio_token` | text, nullable | yes | Auth token |
| `twilio_from` | string, nullable | no | Sender phone number (e.g. `+393331234567`) |
| `google_calendar_id` | string, nullable | no | Calendar ID (e.g. `xxx@group.calendar.google.com`) |
| `google_credentials_json` | text, nullable | yes | Full service account JSON content |

---

## Model: `IntegrationSetting`

- Singleton via `IntegrationSetting::current()` (same pattern as `SystemSetting::current()`)
- `#[Fillable([...])]` attribute with all columns
- `protected function casts()` with `'encrypted'` cast for sensitive fields
- Static getters: `getStripePublicKey()`, `getStripeSecretKey()`, `getStripeWebhookSecret()`, `getTwilioSid()`, `getTwilioToken()`, `getTwilioFrom()`, `getGoogleCalendarId()`, `getGoogleCredentialsJson()`
- All getters return `string|null` (null if not yet set)

---

## Services: Credential Resolution

Each service reads DB credentials first, falls back to `config('services.*')` if the DB value is null/empty. This ensures existing `.env`-based deployments continue working without any changes.

### `PaymentService`

Currently: `StripeClient` is registered as a singleton in `AppServiceProvider` using env keys.

Change: Remove the singleton binding. In `PaymentService`, resolve `StripeClient` lazily at method call time using `IntegrationSetting::getStripeSecretKey() ?? config('services.stripe.secret')`. Same for public key where needed.

### `NotificationService`

Currently: `Twilio\Rest\Client` is registered as a singleton in `AppServiceProvider`.

Change: Remove the singleton binding. In `NotificationService`, instantiate `Twilio\Rest\Client` lazily using `IntegrationSetting::getTwilioSid() ?? config('services.twilio.sid')` and `IntegrationSetting::getTwilioToken() ?? config('services.twilio.token')`. Sender from `IntegrationSetting::getTwilioFrom() ?? config('services.twilio.from')`.

### `GoogleCalendarService`

Currently: `Google\Service\Calendar` is registered as a singleton in `AppServiceProvider`, loading credentials from a file path.

Change: Remove the singleton binding. In `GoogleCalendarService`, resolve credentials lazily: if `IntegrationSetting::getGoogleCredentialsJson()` is set, parse JSON and create `Google_Client` from array; otherwise fall back to file path from config. Calendar ID from `IntegrationSetting::getGoogleCalendarId() ?? config('services.google.calendar_id')`.

---

## Filament Page: `IntegrationSettings`

New page at `app/Filament/Pages/IntegrationSettings.php`, same structure as `SystemSettings.php`.

- Navigation: group "Impostazioni", label "Integrazioni", icon `heroicon-o-puzzle-piece`
- Three sections: "Stripe", "Twilio", "Google Calendar"
- All secret fields use `TextInput::make()->password()->revealable()` (Filament 4 built-in)
- `google_credentials_json` uses `Textarea::make()` (JSON is too long for a password field)
- `mount()` fills form from `IntegrationSetting::current()`
- `save()` calls `IntegrationSetting::current()->update($data)`
- `canAccess()` restricted to admins

### Stripe section fields
- `stripe_public_key` — label "Chiave pubblica (pk_...)", helper "Usata nel frontend per Stripe.js"
- `stripe_secret_key` — label "Chiave segreta (sk_...)", password + revealable
- `stripe_webhook_secret` — label "Webhook secret (whsec_...)", helper "Trovalo nel Stripe Dashboard → Webhook", password + revealable

### Twilio section fields
- `twilio_sid` — label "Account SID", password + revealable
- `twilio_token` — label "Auth Token", password + revealable
- `twilio_from` — label "Numero mittente", helper "Es. +393331234567 (deve essere abilitato per WhatsApp se lo usi)"

### Google Calendar section fields
- `google_calendar_id` — label "Calendar ID", helper "Es. abc123@group.calendar.google.com — trovalo nelle impostazioni del calendario"
- `google_credentials_json` — label "Credenziali Service Account (JSON)", Textarea, helper "Incolla il contenuto del file JSON scaricato da Google Cloud Console → Service Accounts → Chiavi"

---

## AppServiceProvider Cleanup

Remove the three singleton bindings for `StripeClient`, `Twilio\Rest\Client`, and `Google\Service\Calendar` from `AppServiceProvider`. The services will instantiate clients internally.

---

## Fallback Behaviour

If a DB credential is null/empty, the service falls back to the corresponding `.env`/config value. This means:

- Existing deployments with `.env` credentials continue working with zero changes
- The new UI is purely additive
- A salon can migrate to DB credentials gradually (fill in one service at a time)

If both DB and `.env` are empty for a required service, the service throws a `\RuntimeException` with a descriptive message (same as current behaviour when credentials are missing).

---

## Out of Scope

- "Test connessione" buttons per service
- Credential rotation or audit log
- Multi-tenant / per-salon credential isolation
