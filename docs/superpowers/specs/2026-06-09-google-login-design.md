# Google Login per clienti

**Data:** 2026-06-09
**Scope:** Autenticazione OAuth2 con Google per i clienti del portale (non per admin/staff Filament)

## Contesto

Il gestionale è multi-tenant: ogni business (salone) è identificato da un sottodominio. I clienti (`role: customer`) sono legati a un singolo `business_id`. L'autenticazione attuale è custom (niente Breeze/Jetstream): controller dedicati, viste Blade con Tailwind.

## Decisioni chiave

- Un account Google è univoco nel sistema: se lo stesso Google account tenta l'accesso su un sottodominio diverso da quello del salone a cui appartiene, riceve un errore.
- Se esiste già un utente con la stessa email (registrato con password), l'account Google viene collegato automaticamente (`google_id` aggiunto all'utente esistente) e l'utente può d'ora in poi usare entrambi i metodi.
- Il pulsante "Accedi con Google" appare in entrambe le pagine: login e registrazione.
- I nuovi utenti registrati solo via Google non hanno password (campo `password` nullable).

## Database

**Migration:** aggiunge alla tabella `users`:
- `google_id` — `string`, nullable, unique

**Migration:** rende nullable `password` nella tabella `users` (attualmente `NOT NULL`).

## Modello User

Aggiunge `google_id` all'attributo `#[Fillable]`.

## Package

Installa `laravel/socialite`. Configura il driver Google in `config/services.php`:

```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/auth/google/callback',
],
```

Variabili `.env` richieste: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`.

## Controller: SocialAuthController

`app/Http/Controllers/Auth/SocialAuthController.php`

### `redirect()`

Redirige a Google OAuth via `Socialite::driver('google')->redirect()`.

### `callback()`

1. Recupera il profilo da Google (`name`, `email`, `id`)
2. Cerca utente per `google_id`
   - Trovato → login, redirect a `portal.appointments.index`
3. Cerca utente per `email`
   - Trovato: verifica `business_id`
     - Appartiene al business corrente → aggiorna `google_id`, login
     - Appartiene a un altro business → redirect a `/login` con errore: _"Il tuo account è registrato presso un altro salone. Accedi dal sito corretto."_
   - Non trovato → crea nuovo utente:
     - `name` = nome Google
     - `email` = email Google
     - `google_id` = id Google
     - `password` = null
     - `business_id` = `Business::currentId()`
     - ruolo `customer` (via `Role::firstOrCreate`)
   - Login, redirect a `portal.appointments.index`
4. In caso di eccezione OAuth (utente nega permessi, token invalido) → redirect a `/login` con errore generico

## Route

In `routes/web.php`, fuori dal gruppo `guest` e fuori dal gruppo `auth`:

```php
Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->name('auth.google.callback');
```

Il redirect (`/auth/google`) non ha bisogno di middleware `guest` perché Socialite gestirà correttamente anche utenti già autenticati (redirect diretto). Il callback non deve avere `guest` middleware per non bloccare il ritorno da Google.

## Viste

In `resources/views/auth/login.blade.php` e `resources/views/auth/register.blade.php`:

- Aggiunge separatore visivo "oppure" (linea orizzontale con testo centrato)
- Aggiunge pulsante "Accedi con Google" con logo SVG Google, stile outline, che punta a `route('auth.google')`
- Il pulsante viene posizionato sotto il form principale

## Errori e casi limite

| Caso | Comportamento |
|------|--------------|
| Utente nega permessi Google | Redirect a `/login` con flash error generico |
| Email Google già usata su altro business | Redirect a `/login` con messaggio esplicito |
| Eccezione Socialite | Redirect a `/login` con flash error generico |
| Utente già loggato che clicca "Accedi con Google" | Socialite lo redirige comunque, non è un problema |

## Testing

- Test feature: nuovo utente si autentica con Google → utente creato con ruolo customer e business_id corretto
- Test feature: utente esistente (email/password) si autentica con Google → google_id collegato, login riuscito
- Test feature: utente di business A tenta accesso su business B → redirect con errore
- Usare `Socialite::shouldReceive('driver')->...` per mockare nelle unit/feature test
