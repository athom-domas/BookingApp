# Google Login per clienti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere autenticazione OAuth2 con Google per i clienti del portale, con collegamento automatico di account esistenti e vincolo multi-tenant.

**Architecture:** Si installa `laravel/socialite`, si aggiunge `google_id` alla tabella `users` (con `password` resa nullable), si crea `SocialAuthController` con logica redirect/callback, e si aggiornano le viste di login e registrazione con il pulsante Google.

**Tech Stack:** Laravel 13, Laravel Socialite, Spatie Permission, Tailwind CSS (Blade)

---

## File map

| Azione | File |
|--------|------|
| Modifica | `composer.json` — installa `laravel/socialite` |
| Crea | `database/migrations/2026_06_09_300000_add_google_id_to_users.php` |
| Crea | `database/migrations/2026_06_09_300001_make_password_nullable_on_users.php` |
| Modifica | `app/Models/User.php` — aggiunge `google_id` a `#[Fillable]` |
| Modifica | `config/services.php` — aggiunge config Google OAuth |
| Crea | `app/Http/Controllers/Auth/SocialAuthController.php` |
| Modifica | `routes/web.php` — aggiunge due route Google |
| Modifica | `resources/views/auth/login.blade.php` — aggiunge pulsante Google |
| Modifica | `resources/views/auth/register.blade.php` — aggiunge pulsante Google |
| Crea | `tests/Feature/Auth/GoogleLoginTest.php` |

---

## Task 1: Installa laravel/socialite

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Installa il package via Composer nel container**

```bash
docker-compose run --rm --no-deps app composer require laravel/socialite
```

Output atteso: `laravel/socialite` aggiunto a `composer.json` e `composer.lock`.

- [ ] **Step 2: Verifica che il provider sia auto-discoverable**

```bash
docker-compose run --rm --no-deps app php artisan package:discover --ansi
```

Output atteso: nessun errore.

- [ ] **Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: installa laravel/socialite"
```

---

## Task 2: Migration — aggiunge google_id e rende password nullable

**Files:**
- Create: `database/migrations/2026_06_09_300000_add_google_id_to_users.php`
- Create: `database/migrations/2026_06_09_300001_make_password_nullable_on_users.php`

- [ ] **Step 1: Crea la migration per `google_id`**

```bash
docker-compose run --rm app php artisan make:migration add_google_id_to_users
```

Rinomina il file generato in `2026_06_09_300000_add_google_id_to_users.php` e sostituisci il contenuto con:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
```

- [ ] **Step 2: Crea la migration per rendere `password` nullable**

```bash
docker-compose run --rm app php artisan make:migration make_password_nullable_on_users
```

Rinomina il file generato in `2026_06_09_300001_make_password_nullable_on_users.php` e sostituisci il contenuto con:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 3: Esegui le migration**

```bash
docker-compose run --rm app php artisan migrate
```

Output atteso: entrambe le migration eseguite senza errori.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_06_09_300000_add_google_id_to_users.php \
        database/migrations/2026_06_09_300001_make_password_nullable_on_users.php
git commit -m "feat: aggiunge google_id e rende password nullable su users"
```

---

## Task 3: Aggiorna User model e config services

**Files:**
- Modify: `app/Models/User.php`
- Modify: `config/services.php`

- [ ] **Step 1: Aggiorna `#[Fillable]` nel modello User**

In `app/Models/User.php`, riga 26, modifica l'attributo `#[Fillable]` aggiungendo `'google_id'`:

```php
#[Fillable([
    'name', 'email', 'password', 'internal_notes', 'calendar_color',
    'bio', 'receive_email_notifications', 'business_id', 'must_change_password',
    'google_id',
])]
```

- [ ] **Step 2: Aggiorna `config/services.php`**

La chiave `'google'` esiste già (usata per Calendar API). Deve diventare:

```php
'google' => [
    'credentials' => env('GOOGLE_APPLICATION_CREDENTIALS', '/app/config/google-credentials.json'),
    'calendar_id' => env('GOOGLE_CALENDAR_ID'),
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('APP_URL') . '/auth/google/callback',
],
```

- [ ] **Step 3: Aggiunge le variabili in `.env.example`**

In `.env.example`, aggiungi sotto la sezione Google Calendar esistente:

```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
```

- [ ] **Step 4: Commit**

```bash
git add app/Models/User.php config/services.php .env.example
git commit -m "feat: google_id su User fillable, config socialite in services"
```

---

## Task 4: Crea SocialAuthController (TDD)

**Files:**
- Create: `app/Http/Controllers/Auth/SocialAuthController.php`
- Create: `tests/Feature/Auth/GoogleLoginTest.php`

- [ ] **Step 1: Scrivi i test prima dell'implementazione**

Crea `tests/Feature/Auth/GoogleLoginTest.php`:

```php
<?php

use App\Models\Business;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
    app()->instance('current_business_id', 1);
});

function mockSocialiteUser(string $id, string $email, string $name): void
{
    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->id = $id;
    $socialiteUser->email = $email;
    $socialiteUser->name = $name;

    $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('user')->andReturn($socialiteUser);

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redirige a Google OAuth', function () {
    $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
    $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get(route('auth.google'))->assertRedirect();
});

it('crea un nuovo utente customer via Google e fa login', function () {
    $business = Business::factory()->create(['id' => 1]);
    app()->instance('current_business_id', $business->id);

    mockSocialiteUser('google-123', 'nuovo@example.com', 'Nuovo Utente');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $user = User::where('email', 'nuovo@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->google_id)->toBe('google-123');
    expect($user->hasRole('customer'))->toBeTrue();
    expect($user->business_id)->toBe($business->id);
    expect($user->password)->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('collega google_id a utente esistente con stessa email e fa login', function () {
    $business = Business::factory()->create(['id' => 1]);
    app()->instance('current_business_id', $business->id);

    $user = User::factory()->create([
        'email' => 'esistente@example.com',
        'google_id' => null,
        'business_id' => $business->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-456', 'esistente@example.com', 'Utente Esistente');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $user->refresh();
    expect($user->google_id)->toBe('google-456');
    $this->assertAuthenticatedAs($user);
});

it('fa login diretto se google_id già registrato', function () {
    $business = Business::factory()->create(['id' => 1]);
    app()->instance('current_business_id', $business->id);

    $user = User::factory()->create([
        'email' => 'gia@example.com',
        'google_id' => 'google-789',
        'business_id' => $business->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-789', 'gia@example.com', 'Già Registrato');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('portal.appointments.index'));

    $this->assertAuthenticatedAs($user);
});

it('rifiuta utente Google che appartiene a un altro business', function () {
    $businessA = Business::factory()->create();
    $businessB = Business::factory()->create();
    app()->instance('current_business_id', $businessB->id);

    $user = User::factory()->create([
        'email' => 'altrobusiness@example.com',
        'business_id' => $businessA->id,
    ]);
    $user->assignRole('customer');

    mockSocialiteUser('google-999', 'altrobusiness@example.com', 'Altro Business');

    $this->get(route('auth.google.callback'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
```

- [ ] **Step 2: Esegui i test — devono fallire (route non esiste)**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Auth/GoogleLoginTest.php
```

Output atteso: errori su route non trovata o classe non trovata.

- [ ] **Step 3: Crea `SocialAuthController`**

Crea `app/Http/Controllers/Auth/SocialAuthController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Spatie\Permission\Models\Role;
use Throwable;

class SocialAuthController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Accesso con Google non riuscito. Riprova.']);
        }

        $user = User::where('google_id', $googleUser->getId())->first();

        if ($user) {
            Auth::login($user, remember: true);
            return redirect()->intended(route('portal.appointments.index'));
        }

        $user = User::where('email', $googleUser->getEmail())->first();

        if ($user) {
            $currentBusinessId = Business::currentId();

            if ($user->business_id !== $currentBusinessId) {
                return redirect()->route('login')
                    ->withErrors(['email' => 'Il tuo account è registrato presso un altro salone. Accedi dal sito corretto.']);
            }

            $user->update(['google_id' => $googleUser->getId()]);
            Auth::login($user, remember: true);
            return redirect()->intended(route('portal.appointments.index'));
        }

        $user = User::create([
            'name'        => $googleUser->getName(),
            'email'       => $googleUser->getEmail(),
            'google_id'   => $googleUser->getId(),
            'password'    => null,
            'business_id' => Business::currentId(),
        ]);

        Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $user->assignRole('customer');

        Auth::login($user, remember: true);
        return redirect()->intended(route('portal.appointments.index'));
    }
}
```

- [ ] **Step 4: Aggiungi le route in `routes/web.php`**

Dopo le route `storefront.access` e prima del gruppo `guest`, aggiungi:

```php
use App\Http\Controllers\Auth\SocialAuthController;

Route::get('/auth/google', [SocialAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [SocialAuthController::class, 'callback'])->name('auth.google.callback');
```

Assicurati che l'import `SocialAuthController` sia nella sezione `use` in cima al file.

- [ ] **Step 5: Esegui i test — devono passare**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Auth/GoogleLoginTest.php
```

Output atteso: tutti i test passano (verde).

- [ ] **Step 6: Verifica che i test esistenti non siano rotti**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Auth/
```

Output atteso: tutti i test della cartella `Auth` passano.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/Auth/SocialAuthController.php \
        tests/Feature/Auth/GoogleLoginTest.php \
        routes/web.php
git commit -m "feat: SocialAuthController per Google OAuth con test"
```

---

## Task 5: Aggiorna le viste login e registrazione

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`

- [ ] **Step 1: Aggiorna `login.blade.php`**

Sostituisci l'intero contenuto del file con:

```blade
@extends('layouts.app')

@section('title', 'Accesso')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Accedi</h1>

        <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Hai dimenticato la password?</a>
                </div>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 dark:border-gray-600 text-blue-700 focus:ring-blue-200 dark:focus:ring-blue-900">
                Ricordami
            </label>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Accedi
            </button>
        </form>

        <div class="relative mt-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="bg-white dark:bg-gray-900 px-3 text-gray-500 dark:text-gray-400">oppure</span>
            </div>
        </div>

        <a href="{{ route('auth.google') }}" class="mt-4 flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Accedi con Google
        </a>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Non hai un account?
            <a href="{{ route('register') }}{{ request()->filled('return') ? '?return='.urlencode(request('return')) : '' }}" class="font-semibold text-blue-700 hover:text-blue-800">Registrati</a>
        </p>
    </section>
@endsection
```

Nota: è stato aggiunto anche il `@error('email')` per mostrare gli errori di cross-business (messaggio flash tramite `withErrors`).

- [ ] **Step 2: Aggiorna `register.blade.php`**

Sostituisci l'intero contenuto del file con:

```blade
@extends('layouts.app')

@section('title', 'Registrazione')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Crea account cliente</h1>

        <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="name" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Nome</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Conferma password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Crea account
            </button>
        </form>

        <div class="relative mt-6">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-200 dark:border-gray-700"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="bg-white dark:bg-gray-900 px-3 text-gray-500 dark:text-gray-400">oppure</span>
            </div>
        </div>

        <a href="{{ route('auth.google') }}" class="mt-4 flex w-full items-center justify-center gap-3 rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 shadow-sm hover:bg-gray-50 dark:hover:bg-gray-700">
            <svg class="h-5 w-5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Accedi con Google
        </a>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Hai già un account?
            <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Accedi</a>
        </p>
    </section>
@endsection
```

- [ ] **Step 3: Verifica la suite completa di test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest tests/Feature/Auth/
```

Output atteso: tutti i test passano.

- [ ] **Step 4: Commit**

```bash
git add resources/views/auth/login.blade.php resources/views/auth/register.blade.php
git commit -m "feat: aggiunge pulsante Google login/register"
```

---

## Task 6: Verifica finale e suite completa

- [ ] **Step 1: Esegui l'intera suite di test**

```bash
docker-compose run --rm -e DB_DATABASE=booking_app_test app ./vendor/bin/pest
```

Output atteso: nessun fallimento.

- [ ] **Step 2: Verifica configurazione Google in `.env`**

Assicurati di aver impostato le variabili nel tuo `.env` locale:
```
GOOGLE_CLIENT_ID=<il tuo client ID da Google Console>
GOOGLE_CLIENT_SECRET=<il tuo client secret>
```

In Google Cloud Console, nella sezione "Credenziali" → "URI di reindirizzamento autorizzati" deve essere presente:
```
http://localhost/auth/google/callback
```
(o il tuo `APP_URL` + `/auth/google/callback`)

- [ ] **Step 3: Commit finale se non già committato**

```bash
git status
```

Se ci sono modifiche non committate, committarle con messaggio appropriato.
