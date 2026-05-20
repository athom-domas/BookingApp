# Password Reset — Design Spec

**Date:** 2026-05-20  
**Scope:** Portale clienti (`/login`) + Pannello Filament (`/admin`)

---

## Obiettivo

Permettere agli utenti di recuperare l'accesso all'account tramite email nel caso abbiano dimenticato la password. Copre sia il portale clienti che il pannello admin/staff (Filament).

---

## Architettura

### Portale clienti

Flusso in 5 step:

1. Link "Hai dimenticato la password?" nella view `auth/login.blade.php`
2. `GET /password/forgot` → form email (`PasswordResetLinkController@create`)
3. `POST /password/forgot` → invia email con link firmato (`PasswordResetLinkController@store` via `Password::sendResetLink()`)
4. `GET /password/reset/{token}?email=...` → form nuova password (`NewPasswordController@create`)
5. `POST /password/reset` → aggiorna password (`NewPasswordController@store` via `Password::reset()`) → redirect `/login`

**Nuovi file:**
- `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- `app/Http/Controllers/Auth/NewPasswordController.php`
- `resources/views/auth/forgot-password.blade.php`
- `resources/views/auth/reset-password.blade.php`

**Route** (gruppo `guest` in `routes/web.php`):
```
GET   /password/forgot             password.request
POST  /password/forgot             password.email
GET   /password/reset/{token}      password.reset
POST  /password/reset              password.update
```

**Tabella:** `password_reset_tokens` già presente nella migrazione iniziale.  
**Trait:** `User` estende `Authenticatable` che include già `CanResetPassword`.

### Pannello Filament

Aggiungere `->passwordReset()` dopo `->login()` in `AdminPanelProvider.php`. Filament gestisce routing, view e logica in autonomia.

---

## Email

- Dev: Mailhog (già configurato)
- Notifica: `Illuminate\Auth\Notifications\ResetPassword` (built-in Laravel)
- La notifica va localizzata in italiano tramite override del metodo `toMail` o pubblicando le translation strings

---

## View

Stile coerente con `auth/login.blade.php`: layout `layouts.app`, card `max-w-md`, Tailwind, dark mode, testi in italiano.

---

## Gestione errori

- Email non trovata → messaggio generico (non rivelare se l'email esiste)
- Token scaduto/invalido → messaggio di errore + link per richiedere un nuovo reset
- Password troppo corta / non confermata → validazione standard Laravel

---

## Test

File: `tests/Feature/Auth/PasswordResetTest.php`

Casi coperti:
- Mostra form richiesta reset
- Invia link con email valida → success message
- Email non registrata → messaggio generico (no leak)
- Mostra form nuova password con token valido
- Token non valido → errore
- Reset con password non confermata → errore validazione
- Reset riuscito → password aggiornata + redirect login

---

## File modificati

| File | Tipo |
|------|------|
| `app/Providers/Filament/AdminPanelProvider.php` | modifica |
| `routes/web.php` | modifica |
| `resources/views/auth/login.blade.php` | modifica (aggiunta link) |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | nuovo |
| `app/Http/Controllers/Auth/NewPasswordController.php` | nuovo |
| `resources/views/auth/forgot-password.blade.php` | nuovo |
| `resources/views/auth/reset-password.blade.php` | nuovo |
| `tests/Feature/Auth/PasswordResetTest.php` | nuovo |
