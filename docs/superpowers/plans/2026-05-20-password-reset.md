# Password Reset Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add password reset functionality to both the customer portal (`/login`) and the Filament admin panel (`/admin`).

**Architecture:** For the portal, implement the standard Laravel password reset flow with two new controllers, matching Blade views, and 4 new guest routes. For Filament, add `->passwordReset()` to the panel provider — Filament handles everything else. The `password_reset_tokens` table already exists from the initial migration. The `User` model extends `Authenticatable` which already includes the `CanResetPassword` trait.

**Tech Stack:** Laravel 13, PHP 8.4, Filament 4, Pest, Blade/Tailwind

---

## File Map

| File | Action |
|------|--------|
| `app/Providers/Filament/AdminPanelProvider.php` | Modify — add `->passwordReset()` |
| `routes/web.php` | Modify — add 4 guest routes |
| `resources/views/auth/login.blade.php` | Modify — add forgot password link |
| `app/Http/Controllers/Auth/PasswordResetLinkController.php` | Create |
| `app/Http/Controllers/Auth/NewPasswordController.php` | Create |
| `resources/views/auth/forgot-password.blade.php` | Create |
| `resources/views/auth/reset-password.blade.php` | Create |
| `tests/Feature/Auth/PasswordResetTest.php` | Create |

---

## Task 1: Enable Filament Password Reset

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`

- [ ] **Step 1: Add `->passwordReset()` to the panel provider**

In `AdminPanelProvider.php`, add `->passwordReset()` immediately after `->login()`:

```php
->login()
->passwordReset()
->brandName('Booking App')
```

- [ ] **Step 2: Run the test suite to confirm no regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: all existing tests pass.

- [ ] **Step 3: Commit**

```bash
git add app/Providers/Filament/AdminPanelProvider.php
git commit -m "feat: enable filament password reset"
```

---

## Task 2: Write Failing Tests

**Files:**
- Create: `tests/Feature/Auth/PasswordResetTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

beforeEach(function () {
    $this->withoutMiddleware(PreventRequestForgery::class);
});

it('shows the forgot password form', function () {
    $this->get('/password/forgot')->assertOk();
});

it('sends a reset link to a registered email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/password/forgot', ['email' => $user->email])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertSentTo($user, ResetPassword::class);
});

it('does not reveal whether email is registered', function () {
    Notification::fake();

    $this->post('/password/forgot', ['email' => 'notregistered@example.com'])
        ->assertRedirect()
        ->assertSessionHas('status');

    Notification::assertNothingSent();
});

it('shows the new password form with a valid token', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->get("/password/reset/{$token}?email={$user->email}")->assertOk();
});

it('rejects an invalid reset token', function () {
    $user = User::factory()->create();

    $this->post('/password/reset', [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])->assertSessionHasErrors('email');
});

it('rejects mismatched passwords on reset', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'different-password',
    ])->assertSessionHasErrors('password');
});

it('resets the password successfully and redirects to login', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);

    $this->post('/password/reset', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');
});
```

- [ ] **Step 2: Run the tests to confirm they all fail**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php
```
Expected: all 7 tests fail (route not found errors).

---

## Task 3: PasswordResetLinkController + View + Routes

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Auth/PasswordResetLinkController.php`
- Create: `resources/views/auth/forgot-password.blade.php`

- [ ] **Step 1: Add the forgot-password routes in `routes/web.php`**

Add the import at the top of the file with the other auth controller imports:
```php
use App\Http\Controllers\Auth\PasswordResetLinkController;
```

Inside the `Route::middleware('guest')->group(...)` block, after the existing login routes:
```php
Route::get('/password/forgot', [PasswordResetLinkController::class, 'create'])->name('password.request');
Route::post('/password/forgot', [PasswordResetLinkController::class, 'store'])->name('password.email');
```

- [ ] **Step 2: Create the controller**

Create `app/Http/Controllers/Auth/PasswordResetLinkController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Inserisci il tuo indirizzo email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'Se esiste un account con questa email, riceverai a breve un link per reimpostare la password.');
    }
}
```

Note: `store()` always returns the same message regardless of whether the email exists — this prevents user enumeration.

- [ ] **Step 3: Create the view**

Create `resources/views/auth/forgot-password.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Recupera password')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Recupera password</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Inserisci il tuo indirizzo email e ti invieremo un link per reimpostare la password.</p>

        @if (session('status'))
            <p class="mt-4 text-sm font-medium text-green-600 dark:text-green-400">{{ session('status') }}</p>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-5">
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Invia link di recupero
            </button>
        </form>

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Torna al login</a>
        </p>
    </section>
@endsection
```

- [ ] **Step 4: Run the first 3 tests to confirm they pass**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php --filter "forgot|sends a reset link|does not reveal"
```
Expected: 3 tests pass.

---

## Task 4: NewPasswordController + View + Routes

**Files:**
- Modify: `routes/web.php`
- Create: `app/Http/Controllers/Auth/NewPasswordController.php`
- Create: `resources/views/auth/reset-password.blade.php`

- [ ] **Step 1: Add the reset-password routes in `routes/web.php`**

Add the import at the top of the file:
```php
use App\Http\Controllers\Auth\NewPasswordController;
```

Inside the `Route::middleware('guest')->group(...)` block, after the forgot-password routes:
```php
Route::get('/password/reset/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
Route::post('/password/reset', [NewPasswordController::class, 'store'])->name('password.update');
```

- [ ] **Step 2: Create the controller**

Create `app/Http/Controllers/Auth/NewPasswordController.php`:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

class NewPasswordController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.reset-password', ['request' => $request]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)],
        ], [
            'email.required' => 'Inserisci il tuo indirizzo email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'password.required' => 'La password è obbligatoria.',
            'password.confirmed' => 'Le password non coincidono.',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Password reimpostata con successo.')
            : back()->withInput($request->only('email'))
                ->withErrors(['email' => 'Il link di recupero non è valido o è scaduto.']);
    }
}
```

Note: `$password` is the plain text value from the request. The `'password' => 'hashed'` cast on the User model handles hashing automatically on save — do not call `Hash::make()` here.

- [ ] **Step 3: Create the view**

Create `resources/views/auth/reset-password.blade.php`:

```blade
@extends('layouts.app')

@section('title', 'Nuova password')

@section('content')
    <section class="mx-auto max-w-md rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
        <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Nuova password</h1>

        <form method="POST" action="{{ route('password.update') }}" class="mt-6 space-y-5">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div>
                <label for="email" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Email</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus
                    class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
                @error('email')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Nuova password</label>
                <input id="password" name="password" type="password" required
                    class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
                @error('password')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Conferma password</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required
                    class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>

            <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Reimposta password
            </button>
        </form>
    </section>
@endsection
```

- [ ] **Step 4: Run all 7 password reset tests**

```bash
docker-compose run --rm app ./vendor/bin/pest tests/Feature/Auth/PasswordResetTest.php
```
Expected: all 7 tests pass.

- [ ] **Step 5: Commit**

```bash
git add routes/web.php \
    app/Http/Controllers/Auth/PasswordResetLinkController.php \
    app/Http/Controllers/Auth/NewPasswordController.php \
    resources/views/auth/forgot-password.blade.php \
    resources/views/auth/reset-password.blade.php \
    tests/Feature/Auth/PasswordResetTest.php
git commit -m "feat: add portal password reset flow"
```

---

## Task 5: Add Forgot Password Link to Login View

**Files:**
- Modify: `resources/views/auth/login.blade.php`

- [ ] **Step 1: Update the password field block in `login.blade.php`**

Replace:
```blade
            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>
```

With:
```blade
            <div>
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-blue-700 hover:text-blue-800">Hai dimenticato la password?</a>
                </div>
                <input id="password" name="password" type="password" required class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900">
            </div>
```

- [ ] **Step 2: Run the full test suite to confirm no regressions**

```bash
docker-compose run --rm app ./vendor/bin/pest
```
Expected: all tests pass.

- [ ] **Step 3: Commit**

```bash
git add resources/views/auth/login.blade.php
git commit -m "feat: add forgot password link to login view"
```
