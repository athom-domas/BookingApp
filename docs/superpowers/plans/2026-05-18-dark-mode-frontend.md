# Dark Mode Frontend Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add dark mode (OS default + manual toggle) to all customer-facing frontend pages.

**Architecture:** Tailwind v4 `@custom-variant dark` makes `dark:` utilities respond to the `.dark` class on `<html>`. An inline anti-FOUC script in the layout `<head>` reads `localStorage.theme` (or falls back to OS preference) and sets the class before styles paint. A toggle button in the navbar writes to `localStorage`. Public standalone pages use CSS custom properties + `@media` + `html.dark` selectors.

**Tech Stack:** Tailwind CSS v4, Alpine.js 3, Laravel Blade

> **Note on TDD:** These tasks are purely visual/CSS. There are no meaningful unit tests to write first. Each task ends with a visual verification step (run dev server, toggle dark/light, inspect visually).

---

## File Map

| File | Change |
|---|---|
| `resources/css/app.css` | Add `@custom-variant dark` |
| `resources/views/layouts/app.blade.php` | Anti-FOUC script, body dark classes, toggle button |
| `resources/views/auth/login.blade.php` | `dark:` classes on card, inputs, labels |
| `resources/views/auth/register.blade.php` | Same as login |
| `resources/views/welcome.blade.php` | `dark:` classes on hero, service cards |
| `resources/views/portal/appointments/index.blade.php` | `dark:` on headings/text |
| `resources/views/portal/appointments/partials/list.blade.php` | `dark:` on table, header, borders |
| `resources/views/portal/appointments/partials/status-badge.blade.php` | Dark variants in PHP `match` |
| `resources/views/portal/appointments/partials/payment-badge.blade.php` | Dark variants in PHP `match` |
| `resources/views/portal/appointments/show.blade.php` | `dark:` on cards, dl, textarea |
| `resources/views/portal/appointments/payment.blade.php` | `dark:` on cards, warning box |
| `resources/views/portal/booking/index.blade.php` | `dark:` on all wizard steps + Alpine `:class` bindings |
| `resources/views/public/appointment-confirmed.blade.php` | CSS custom properties + `html.dark` selectors |
| `resources/views/public/appointment-cancelled.blade.php` | Same |
| `resources/views/public/appointment-cancel.blade.php` | Same |

---

## Task 1: Configure Tailwind v4 dark variant

**Files:**
- Modify: `resources/css/app.css`

- [ ] **Step 1: Add `@custom-variant` to `app.css`**

Replace the contents of `resources/css/app.css` with:

```css
@import 'tailwindcss';

@source '../../vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php';
@source '../../storage/framework/views/*.php';
@source '../**/*.blade.php';
@source '../**/*.js';

@custom-variant dark (&:where(.dark, .dark *));

@theme {
    --font-sans: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji',
        'Segoe UI Symbol', 'Noto Color Emoji';
}
```

- [ ] **Step 2: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: configure Tailwind v4 dark variant via custom-variant"
```

---

## Task 2: Layout — anti-FOUC script, body classes, toggle button

**Files:**
- Modify: `resources/views/layouts/app.blade.php`

- [ ] **Step 1: Replace `layouts/app.blade.php`**

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Booking App') - Booking App</title>

        <script>
            if (localStorage.theme === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        </script>

        @fonts
        @filamentStyles
        @vite('resources/css/app.css')
        @vite('resources/css/filament/admin/theme.css')
        @stack('head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-gray-950 font-sans text-gray-950 dark:text-gray-50 antialiased">
        <header class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('booking.index') }}" class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    <span class="truncate text-base font-semibold text-gray-950 dark:text-gray-50">Booking App</span>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium">
                    <a href="{{ route('booking.create') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Prenota</a>
                    @auth
                        <a href="{{ route('portal.appointments.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Appuntamenti</a>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <a href="{{ url('/admin') }}" class="hidden rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50 sm:inline-flex">Admin</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Esci</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Accedi</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-blue-700 px-3 py-2 text-white hover:bg-blue-800">Registrati</a>
                    @endauth

                    <button
                        x-data
                        @click="
                            document.documentElement.classList.toggle('dark');
                            localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        "
                        class="rounded-md p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50"
                        aria-label="Cambia tema"
                    >
                        <svg class="hidden dark:block h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="block dark:hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-950 px-4 py-3 text-sm text-red-800 dark:text-red-300">
                    <p class="font-semibold">Controlla i dati inseriti.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>

        @stack('scripts')
        @filamentScripts
        @vite('resources/js/app.js')
    </body>
</html>
```

- [ ] **Step 2: Start dev server and verify visually**

```bash
docker-compose up -d
```

Open `http://localhost` in a browser. Toggle dark mode from OS settings — background should switch. Click the sun/moon button in the navbar — it should persist across page reloads.

- [ ] **Step 3: Commit**

```bash
git add resources/views/layouts/app.blade.php
git commit -m "feat: add dark mode anti-FOUC script and toggle button to layout"
```

---

## Task 3: Auth views (login + register)

**Files:**
- Modify: `resources/views/auth/login.blade.php`
- Modify: `resources/views/auth/register.blade.php`

- [ ] **Step 1: Replace `auth/login.blade.php`**

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
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Password</label>
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

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Non hai un account?
            <a href="{{ route('register') }}" class="font-semibold text-blue-700 hover:text-blue-800">Registrati</a>
        </p>
    </section>
@endsection
```

- [ ] **Step 2: Replace `auth/register.blade.php`**

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

        <p class="mt-5 text-sm text-gray-600 dark:text-gray-400">
            Hai gia un account?
            <a href="{{ route('login') }}" class="font-semibold text-blue-700 hover:text-blue-800">Accedi</a>
        </p>
    </section>
@endsection
```

- [ ] **Step 3: Verify visually**

Open `http://localhost/login` and `http://localhost/register` in dark mode. Card, inputs, labels should all render correctly.

- [ ] **Step 4: Commit**

```bash
git add resources/views/auth/login.blade.php resources/views/auth/register.blade.php
git commit -m "feat: add dark mode to auth views"
```

---

## Task 4: Welcome page

**Files:**
- Modify: `resources/views/welcome.blade.php`

- [ ] **Step 1: Replace `welcome.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Benvenuto')

@section('content')
    <section class="space-y-12">
        <div class="text-center space-y-4 py-12">
            <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">Booking App</p>
            <h1 class="text-4xl font-bold text-gray-950 dark:text-gray-50 sm:text-5xl">
                Prenota il tuo appuntamento
            </h1>
            <p class="mx-auto max-w-xl text-base leading-7 text-gray-600 dark:text-gray-400">
                Scegli tra i nostri servizi, seleziona il professionista e trova l'orario che fa per te.
            </p>
            <a href="{{ route('booking.create') }}"
               class="inline-block rounded-md bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Prenota ora
            </a>
        </div>

        <div class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-gray-50">I nostri servizi</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($services as $service)
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ $service->name }}</h3>
                            <span class="shrink-0 rounded-md bg-blue-50 dark:bg-blue-950 px-2.5 py-1 text-sm font-semibold text-blue-700 dark:text-blue-300">
                                {{ number_format((float) $service->price, 2, ',', '.') }} €
                            </span>
                        </div>
                        @if ($service->description)
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $service->description }}</p>
                        @endif
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-500">Durata: {{ $service->duration_minutes }} min</p>
                    </article>
                @empty
                    <p class="col-span-full text-sm text-gray-500 dark:text-gray-500">Nessun servizio attivo al momento.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
```

- [ ] **Step 2: Verify visually**

Open `http://localhost` in dark mode. Hero text, service cards should render correctly.

- [ ] **Step 3: Commit**

```bash
git add resources/views/welcome.blade.php
git commit -m "feat: add dark mode to welcome page"
```

---

## Task 5: Appointments index + list partial

**Files:**
- Modify: `resources/views/portal/appointments/index.blade.php`
- Modify: `resources/views/portal/appointments/partials/list.blade.php`

- [ ] **Step 1: Replace `portal/appointments/index.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'I miei appuntamenti')

@section('content')
    <section class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold text-gray-950 dark:text-gray-50">I miei appuntamenti</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Prenotazioni future, pagamenti e storico.</p>
            </div>
            <a href="{{ route('booking.create') }}" class="rounded-md bg-blue-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Nuova prenotazione</a>
        </div>

        @include('portal.appointments.partials.list', [
            'title' => 'Prossimi appuntamenti',
            'appointments' => $upcomingAppointments,
            'empty' => 'Non hai appuntamenti futuri.',
        ])

        @include('portal.appointments.partials.list', [
            'title' => 'Storico',
            'appointments' => $pastAppointments,
            'empty' => 'Lo storico e vuoto.',
        ])
    </section>
@endsection
```

- [ ] **Step 2: Replace `portal/appointments/partials/list.blade.php`**

```blade
<section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-gray-50">{{ $title }}</h2>
    </div>

    @if ($appointments->isEmpty())
        <p class="px-5 py-6 text-sm text-gray-600 dark:text-gray-400">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs font-semibold uppercase tracking-normal text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Servizio</th>
                        <th class="px-5 py-3">Staff</th>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Stato</th>
                        <th class="px-5 py-3">Pagamento</th>
                        <th class="px-5 py-3 text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    @foreach ($appointments as $appointment)
                        <tr>
                            <td class="px-5 py-4 font-medium text-gray-950 dark:text-gray-50">{{ $appointment->service->name }}</td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">{{ $appointment->staff->name }}</td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
                            </td>
                            <td class="px-5 py-4">
                                @if ($appointment->payment)
                                    @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                                @else
                                    <span class="rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300">Assente</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('portal.appointments.show', $appointment) }}" class="rounded-md border border-gray-300 dark:border-gray-600 px-3 py-2 text-xs font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Dettaglio</a>
                                    @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                                        <a href="{{ route('portal.appointments.payment', $appointment) }}" class="rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Paga</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
```

- [ ] **Step 3: Verify visually**

Open `http://localhost/portal/appointments` in dark mode. Table headers, rows, borders should render correctly.

- [ ] **Step 4: Commit**

```bash
git add resources/views/portal/appointments/index.blade.php resources/views/portal/appointments/partials/list.blade.php
git commit -m "feat: add dark mode to appointments index and list partial"
```

---

## Task 6: Status badge + payment badge

**Files:**
- Modify: `resources/views/portal/appointments/partials/status-badge.blade.php`
- Modify: `resources/views/portal/appointments/partials/payment-badge.blade.php`

- [ ] **Step 1: Replace `status-badge.blade.php`**

```blade
@php
    $classes = match ($status) {
        'pending'   => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'confirmed' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'completed' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        default     => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $label = match ($status) {
        'pending'   => 'In attesa',
        'confirmed' => 'Confermato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        default     => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
```

- [ ] **Step 2: Replace `payment-badge.blade.php`**

```blade
@php
    $classes = match ($status) {
        'pending'            => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300',
        'completed'          => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'refunded'           => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        'failed', 'cancelled'=> 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        default              => 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    };

    $label = match ($status) {
        'pending'   => 'In attesa',
        'completed' => 'Pagato',
        'refunded'  => 'Rimborsato',
        'failed'    => 'Fallito',
        'cancelled' => 'Annullato',
        default     => $status,
    };
@endphp

<span class="rounded-md px-2 py-1 text-xs font-medium {{ $classes }}">{{ $label }}</span>
```

- [ ] **Step 3: Verify visually**

Open appointment list in dark mode. Status and payment badges should show muted dark backgrounds with readable text.

- [ ] **Step 4: Commit**

```bash
git add resources/views/portal/appointments/partials/status-badge.blade.php resources/views/portal/appointments/partials/payment-badge.blade.php
git commit -m "feat: add dark mode to status and payment badges"
```

---

## Task 7: Appointment show view

**Files:**
- Modify: `resources/views/portal/appointments/show.blade.php`

- [ ] **Step 1: Replace `portal/appointments/show.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Dettaglio appuntamento')

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">{{ $appointment->service->name }}</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</p>
                </div>
                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
            </div>

            <dl class="mt-8 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Staff</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->staff->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Durata</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->service->duration_minutes }} min</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Prezzo</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ number_format((float) $appointment->final_price, 2, ',', '.') }} euro</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Pagamento</dt>
                    <dd class="mt-1">
                        @if ($appointment->payment)
                            @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-500">Nessun pagamento</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($appointment->notes)
                <div class="mt-8">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Note</h2>
                    <p class="mt-2 whitespace-pre-line rounded-md bg-gray-50 dark:bg-gray-800 p-4 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $appointment->notes }}</p>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                <a href="{{ route('portal.appointments.payment', $appointment) }}" class="block rounded-md bg-blue-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Completa pagamento</a>
            @endif

            @if ($appointment->canBeCancelled())
                <form method="POST" action="{{ route('portal.appointments.cancel', $appointment) }}" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                    @csrf
                    <label for="reason" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Motivo cancellazione</label>
                    <textarea id="reason" name="reason" rows="3" maxlength="1000" class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900"></textarea>
                    <button type="submit" class="mt-4 w-full rounded-md border border-red-300 dark:border-red-700 px-4 py-3 text-sm font-semibold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">
                        Cancella prenotazione
                    </button>
                </form>
            @endif

            <a href="{{ route('portal.appointments.index') }}" class="block rounded-md border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Torna agli appuntamenti</a>
        </aside>
    </section>
@endsection
```

- [ ] **Step 2: Verify visually**

Open an appointment detail in dark mode. Card, dl, cancel form, textarea, buttons should all render correctly.

- [ ] **Step 3: Commit**

```bash
git add resources/views/portal/appointments/show.blade.php
git commit -m "feat: add dark mode to appointment show view"
```

---

## Task 8: Payment view

**Files:**
- Modify: `resources/views/portal/appointments/payment.blade.php`

- [ ] **Step 1: Replace `portal/appointments/payment.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Pagamento')

@push('head')
    <script src="https://js.stripe.com/v3/"></script>
@endpush

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">Pagamento prenotazione</h1>
            <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Servizio</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->service->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Data</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Staff</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->staff->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Importo</dt>
                    <dd class="mt-1 text-base font-semibold text-gray-950 dark:text-gray-50">{{ number_format((float) $payment->amount, 2, ',', '.') }} euro</dd>
                </div>
            </dl>
        </div>

        <aside class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            @if ($stripePublicKey && $clientSecret)
                <form data-stripe-payment data-public-key="{{ $stripePublicKey }}" data-client-secret="{{ $clientSecret }}" class="space-y-5">
                    <div id="payment-element" class="min-h-32 rounded-md border border-gray-200 dark:border-gray-700 p-3"></div>
                    <p class="hidden text-sm text-red-700 dark:text-red-400" data-payment-error></p>
                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                        Paga ora
                    </button>
                </form>

                <form method="POST" action="{{ route('portal.appointments.payment.confirm', $appointment) }}" data-payment-confirm-form class="hidden">
                    @csrf
                </form>
            @else
                <div class="rounded-md border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950 p-4 text-sm text-yellow-900 dark:text-yellow-300">
                    Configurazione Stripe incompleta. Verifica chiave pubblica e client secret del PaymentIntent.
                </div>
            @endif
        </aside>
    </section>
@endsection
```

- [ ] **Step 2: Verify visually**

Open the payment page in dark mode. Both columns, the Stripe container, and the warning box should render correctly.

- [ ] **Step 3: Commit**

```bash
git add resources/views/portal/appointments/payment.blade.php
git commit -m "feat: add dark mode to payment view"
```

---

## Task 9: Booking wizard

**Files:**
- Modify: `resources/views/portal/booking/index.blade.php`

This is the most class-heavy file. Static Tailwind classes and Alpine.js `:class` bindings both need updating.

- [ ] **Step 1: Replace `portal/booking/index.blade.php`**

```blade
@extends('layouts.app')

@section('title', 'Nuova prenotazione')

@section('content')
    @php
        $servicesJson = $services->map(fn ($s) => [
            'id'          => $s->id,
            'name'        => $s->name,
            'description' => $s->description ?? '',
            'duration'    => $s->duration_minutes,
            'price'       => (float) $s->price,
            'staff_ids'   => $s->staff->pluck('id')->values()->all(),
        ])->values()->all();

        $staffJson = $staff->map(fn ($m) => [
            'id'          => $m->id,
            'name'        => $m->name,
            'service_ids' => $m->services->pluck('id')->values()->all(),
        ])->values()->all();
    @endphp

    <div class="mx-auto max-w-2xl space-y-4">
        <div class="space-y-1">
            <h1 class="text-2xl font-bold text-gray-950 dark:text-gray-50">Nuova prenotazione</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400">Completa i passi in ordine per prenotare il tuo appuntamento.</p>
        </div>

        <div
            x-data="bookingWizard({{ Illuminate\Support\Js::from($servicesJson) }}, {{ Illuminate\Support\Js::from($staffJson) }})"
            class="space-y-3"
        >
            {{-- CSRF + hidden inputs per il form submit --}}
            <form method="POST" action="{{ route('portal.bookings.store') }}" x-ref="bookingForm">
                @csrf
                <template x-for="id in selectedServiceIds" :key="id">
                    <input type="hidden" name="service_ids[]" :value="id">
                </template>
                <input type="hidden" name="staff_id" :value="staffId ?? ''">
                <input type="hidden" name="scheduled_date" :value="scheduledDateTime">
                <input type="hidden" name="payment_method" :value="paymentMethod ?? ''">
                <input type="hidden" name="notes" :value="notes">
            </form>

            {{-- Step 1: Servizi --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(1) && !isOpen(1) ? goTo(1) : null"
                    :class="isCompleted(1) && !isOpen(1) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(1) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(1)">1</span>
                            <svg x-show="isCompleted(1)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli i servizi</p>
                            <p x-show="isCompleted(1) && !isOpen(1)" class="text-xs text-gray-500 dark:text-gray-400" x-text="servicesSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(1)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(1)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <template x-for="service in allServices" :key="service.id">
                            <button
                                type="button"
                                @click="toggleService(service.id)"
                                class="rounded-lg border p-4 text-left transition-colors"
                                :class="isSelectedService(service.id)
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950 dark:border-blue-500'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <span class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="service.name"></span>
                                    <span class="shrink-0 rounded bg-blue-50 dark:bg-blue-950 px-1.5 py-0.5 text-xs font-semibold text-blue-700 dark:text-blue-300"
                                          x-text="'€ ' + service.price.toFixed(2).replace('.', ',')"></span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-text="service.description"></p>
                                <p class="mt-2 text-xs text-gray-400 dark:text-gray-500" x-text="service.duration + ' min'"></p>
                            </button>
                        </template>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <p x-show="selectedServiceIds.length > 0" class="text-xs text-gray-500 dark:text-gray-400"
                           x-text="'Totale: ' + totalDuration + ' min · € ' + totalPrice.toFixed(2).replace('.', ',')"></p>
                        <button
                            type="button"
                            @click="completeStep(1)"
                            :disabled="selectedServiceIds.length === 0"
                            class="ml-auto rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 2: Operatore --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(1) && !isOpen(2) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(2) && !isOpen(2) ? goTo(2) : null"
                    :class="isCompleted(2) && !isOpen(2) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(2) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(2)">2</span>
                            <svg x-show="isCompleted(2)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli l'operatore</p>
                            <p x-show="isCompleted(2) && !isOpen(2)" class="text-xs text-gray-500 dark:text-gray-400" x-text="staffSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(2)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(2)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="space-y-2">
                        <button
                            type="button"
                            @click="staffId = null"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="staffId === null
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950 dark:border-blue-500'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Qualsiasi operatore disponibile</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Il sistema assegnerà il miglior operatore libero</p>
                        </button>

                        <template x-for="member in filteredStaff" :key="member.id">
                            <button
                                type="button"
                                @click="staffId = member.id"
                                class="w-full rounded-lg border p-4 text-left transition-colors"
                                :class="staffId === member.id
                                    ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950 dark:border-blue-500'
                                    : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                            >
                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="member.name"></p>
                            </button>
                        </template>

                        <p x-show="filteredStaff.length === 0" class="text-sm text-gray-500 dark:text-gray-400">
                            Nessun operatore disponibile per tutti i servizi selezionati.
                        </p>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(2)"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 3: Data e ora --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(2) && !isOpen(3) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(3) && !isOpen(3) ? goTo(3) : null"
                    :class="isCompleted(3) && !isOpen(3) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(3) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(3)">3</span>
                            <svg x-show="isCompleted(3)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Scegli data e ora</p>
                            <p x-show="isCompleted(3) && !isOpen(3)" class="text-xs text-gray-500 dark:text-gray-400" x-text="dateSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(3)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(3)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="mb-4 flex items-center justify-between">
                        <button type="button" @click="prevMonth()" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100" x-text="monthLabel"></p>
                        <button type="button" @click="nextMonth()" class="rounded p-1 hover:bg-gray-100 dark:hover:bg-gray-800">
                            <svg class="h-4 w-4 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-7 gap-1 text-center">
                        <template x-for="d in ['Lu','Ma','Me','Gi','Ve','Sa','Do']">
                            <div class="py-1 text-xs font-medium text-gray-400 dark:text-gray-500" x-text="d"></div>
                        </template>
                        <template x-for="(cell, i) in calendarGrid" :key="i">
                            <div>
                                <template x-if="cell === null">
                                    <div></div>
                                </template>
                                <template x-if="cell !== null">
                                    <button
                                        type="button"
                                        @click="selectDate(cell)"
                                        :disabled="!isAvailableDate(cell)"
                                        class="w-full rounded-md py-1.5 text-sm transition-colors"
                                        :class="{
                                            'bg-blue-700 text-white font-semibold': date === cell,
                                            'hover:bg-blue-50 dark:hover:bg-blue-950 text-gray-900 dark:text-gray-100': isAvailableDate(cell) && date !== cell,
                                            'text-gray-300 dark:text-gray-600 cursor-not-allowed': !isAvailableDate(cell),
                                        }"
                                        x-text="cell.split('-')[2]"
                                    ></button>
                                </template>
                            </div>
                        </template>
                    </div>

                    <div x-show="loadingDates" class="mt-3 text-center text-xs text-gray-500 dark:text-gray-400">Caricamento disponibilità...</div>

                    <div x-show="date !== null" class="mt-4">
                        <p class="mb-2 text-xs font-medium text-gray-700 dark:text-gray-300">Orari disponibili</p>
                        <div x-show="loadingSlots" class="text-xs text-gray-500 dark:text-gray-400">Caricamento orari...</div>
                        <div x-show="!loadingSlots && availableSlots.length === 0 && date !== null" class="text-xs text-gray-500 dark:text-gray-400">
                            Nessun orario disponibile per questa data.
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="s in availableSlots" :key="s.start">
                                <button
                                    type="button"
                                    @click="slot = s.start"
                                    class="rounded-full border px-3 py-1 text-xs font-medium transition-colors"
                                    :class="slot === s.start
                                        ? 'border-blue-600 bg-blue-700 text-white'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:border-blue-400 hover:text-blue-700 dark:hover:border-blue-500 dark:hover:text-blue-400'"
                                    x-text="s.start"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(3)"
                            :disabled="!date || !slot"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 4: Metodo di pagamento --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(3) && !isOpen(4) ? 'opacity-50' : ''">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-5 py-4 text-left"
                    @click="isCompleted(4) && !isOpen(4) ? goTo(4) : null"
                    :class="isCompleted(4) && !isOpen(4) ? 'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800' : 'cursor-default'"
                >
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold"
                              :class="isCompleted(4) ? 'bg-blue-700 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'">
                            <span x-show="!isCompleted(4)">4</span>
                            <svg x-show="isCompleted(4)" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Metodo di pagamento</p>
                            <p x-show="isCompleted(4) && !isOpen(4)" class="text-xs text-gray-500 dark:text-gray-400" x-text="paymentSummary"></p>
                        </div>
                    </div>
                    <svg x-show="isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                    <svg x-show="!isOpen(4)" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                </button>
                <div x-show="isOpen(4)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4">
                    <div class="space-y-3">
                        <button
                            type="button"
                            @click="paymentMethod = 'online'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'online'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950 dark:border-blue-500'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Paga ora</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Pagamento online con carta — la prenotazione viene confermata solo al completamento del pagamento</p>
                        </button>
                        <button
                            type="button"
                            @click="paymentMethod = 'in_salon'"
                            class="w-full rounded-lg border p-4 text-left transition-colors"
                            :class="paymentMethod === 'in_salon'
                                ? 'border-blue-600 bg-blue-50 ring-1 ring-blue-600 dark:bg-blue-950 dark:border-blue-500'
                                : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:hover:border-gray-600 dark:hover:bg-gray-800'"
                        >
                            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Paga in salone</p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Paghi direttamente al momento del servizio — la prenotazione è confermata subito</p>
                        </button>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <button
                            type="button"
                            @click="completeStep(4)"
                            :disabled="paymentMethod === null"
                            class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Continua
                        </button>
                    </div>
                </div>
            </div>

            {{-- Step 5: Riepilogo e conferma --}}
            <div class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm"
                 :class="!isCompleted(4) && !isOpen(5) ? 'opacity-50' : ''">
                <div class="flex items-center gap-3 px-5 py-4">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-800 text-xs font-bold text-gray-600 dark:text-gray-400">5</span>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Riepilogo e conferma</p>
                </div>
                <div x-show="isOpen(5)" class="border-t border-gray-100 dark:border-gray-700 px-5 pb-5 pt-4 space-y-4">
                    @auth
                        <dl class="space-y-2 rounded-lg bg-gray-50 dark:bg-gray-800 p-4 text-sm">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Servizi</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="selectedServiceIds.map(id => serviceById(id)?.name).join(', ')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Operatore</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="staffId ? (allStaff.find(s => s.id === staffId)?.name ?? '—') : 'Qualsiasi operatore'"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Data e ora</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="dateSummary"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Durata</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="totalDuration + ' min'"></dd>
                            </div>
                            <div class="flex justify-between border-t border-gray-200 dark:border-gray-700 pt-2">
                                <dt class="font-semibold text-gray-900 dark:text-gray-100">Totale</dt>
                                <dd class="font-bold text-gray-900 dark:text-gray-100" x-text="'€ ' + totalPrice.toFixed(2).replace('.', ',')"></dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Pagamento</dt>
                                <dd class="font-medium text-gray-900 dark:text-gray-100" x-text="paymentSummary"></dd>
                            </div>
                        </dl>

                        <div>
                            <label class="block text-sm font-medium text-gray-900 dark:text-gray-200">Note (opzionale)</label>
                            <textarea
                                x-model="notes"
                                rows="3"
                                maxlength="1000"
                                class="mt-1 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-950 dark:text-gray-50 shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900"
                            ></textarea>
                        </div>

                        <button
                            type="button"
                            @click="$refs.bookingForm.submit()"
                            class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800"
                            x-text="paymentMethod === 'online' ? 'Prenota e vai al pagamento' : 'Conferma prenotazione'"
                        ></button>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-400">Accedi o crea un account per completare la prenotazione.</p>
                        <div class="flex gap-3">
                            <a href="{{ route('login') }}" class="flex-1 rounded-md border border-gray-300 dark:border-gray-600 px-4 py-2.5 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Accedi</a>
                            <a href="{{ route('register') }}" class="flex-1 rounded-md bg-blue-700 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-blue-800">Crea account</a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </div>
@endsection
```

- [ ] **Step 2: Verify visually**

Open `http://localhost/booking/create` in dark mode. Walk through all 5 steps — step headers, card selections, calendar, time slots, summary table should all render correctly.

- [ ] **Step 3: Commit**

```bash
git add resources/views/portal/booking/index.blade.php
git commit -m "feat: add dark mode to booking wizard"
```

---

## Task 10: Public standalone pages

**Files:**
- Modify: `resources/views/public/appointment-confirmed.blade.php`
- Modify: `resources/views/public/appointment-cancelled.blade.php`
- Modify: `resources/views/public/appointment-cancel.blade.php`

These pages use inline `<style>` only (no Tailwind). Dark mode is added via CSS custom properties on `html.dark`. The anti-FOUC script handles both localStorage and OS preference detection (via `matchMedia`) before styles paint — no `@media (prefers-color-scheme: dark)` block is needed in the CSS.

- [ ] **Step 1: Replace `public/appointment-confirmed.blade.php`**

```blade
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appuntamento confermato</title>
    <script>
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
    <style>
        :root {
            --color-body-bg: #fff;
            --color-body-text: #333;
            --color-card-bg: #f9fafb;
            --color-muted: #6b7280;
            --color-strong: #111;
        }
        html.dark {
            --color-body-bg: #111827;
            --color-body-text: #e5e7eb;
            --color-card-bg: #1f2937;
            --color-muted: #9ca3af;
            --color-strong: #f9fafb;
        }
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: var(--color-body-text); background: var(--color-body-bg); }
        .card { background: var(--color-card-bg); border-radius: 12px; padding: 32px; text-align: center; }
        h1 { color: #16a34a; font-size: 1.5rem; margin-bottom: 8px; }
        p { color: var(--color-muted); }
        .detail { margin: 20px 0; font-size: 1rem; }
        strong { color: var(--color-strong); }
    </style>
</head>
<body>
    <div class="card">
        @if($alreadyPast)
            <h1>Appuntamento non disponibile</h1>
            <p>Questo appuntamento è già passato o annullato.</p>
        @else
            <h1>✓ Perfetto, ci vediamo!</h1>
            <p>Abbiamo registrato la tua conferma.</p>
            <div class="detail">
                <strong>{{ $appointment->service->name }}</strong><br>
                {{ $appointment->scheduled_date->format('d/m/Y') }} alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong><br>
                con {{ $appointment->staff->name }}
            </div>
        @endif
    </div>
</body>
</html>
```

- [ ] **Step 2: Replace `public/appointment-cancelled.blade.php`**

```blade
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appuntamento annullato</title>
    <script>
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
    <style>
        :root {
            --color-body-bg: #fff;
            --color-body-text: #333;
            --color-card-bg: #f9fafb;
            --color-muted: #6b7280;
        }
        html.dark {
            --color-body-bg: #111827;
            --color-body-text: #e5e7eb;
            --color-card-bg: #1f2937;
            --color-muted: #9ca3af;
        }
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: var(--color-body-text); background: var(--color-body-bg); }
        .card { background: var(--color-card-bg); border-radius: 12px; padding: 32px; text-align: center; }
        h1 { font-size: 1.5rem; margin-bottom: 8px; }
        p { color: var(--color-muted); }
    </style>
</head>
<body>
    <div class="card">
        @if($alreadyDone)
            <h1>Già annullato</h1>
            <p>Questo appuntamento non può essere annullato.</p>
        @else
            <h1>Appuntamento annullato</h1>
            <p>Il tuo appuntamento è stato annullato. Puoi prenotarne uno nuovo quando vuoi.</p>
        @endif
    </div>
</body>
</html>
```

- [ ] **Step 3: Replace `public/appointment-cancel.blade.php`**

```blade
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disdici appuntamento</title>
    <script>
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
    <style>
        :root {
            --color-body-bg: #fff;
            --color-body-text: #333;
            --color-card-bg: #f9fafb;
            --color-detail-bg: #fff;
            --color-border: #d1d5db;
            --color-label: #374151;
            --color-textarea-text: #111;
        }
        html.dark {
            --color-body-bg: #111827;
            --color-body-text: #e5e7eb;
            --color-card-bg: #1f2937;
            --color-detail-bg: #111827;
            --color-border: #4b5563;
            --color-label: #d1d5db;
            --color-textarea-text: #f9fafb;
        }
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: var(--color-body-text); background: var(--color-body-bg); }
        .card { background: var(--color-card-bg); border-radius: 12px; padding: 32px; }
        h1 { color: #dc2626; font-size: 1.5rem; margin-bottom: 8px; }
        .detail { margin: 16px 0; padding: 16px; background: var(--color-detail-bg); border-radius: 8px; font-size: 0.95rem; }
        textarea { width: 100%; padding: 10px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 0.95rem; resize: vertical; box-sizing: border-box; background: var(--color-detail-bg); color: var(--color-textarea-text); }
        button { width: 100%; padding: 12px; background: #dc2626; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 16px; }
        button:hover { background: #b91c1c; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: var(--color-label); }
    </style>
</head>
<body>
    <div class="card">
        <h1>Disdici appuntamento</h1>
        <div class="detail">
            <strong>{{ $appointment->service->name }}</strong><br>
            {{ $appointment->scheduled_date->format('d/m/Y') }} alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong><br>
            con {{ $appointment->staff->name }}
        </div>
        <form method="POST" action="{{ request()->url() }}">
            @csrf
            <label for="reason">Motivo (opzionale)</label>
            <textarea id="reason" name="reason" rows="3" placeholder="Es. impegno improvviso..."></textarea>
            <button type="submit">Conferma annullamento</button>
        </form>
    </div>
</body>
</html>
```

- [ ] **Step 4: Verify visually**

Open each public page URL directly in a browser in dark mode. All three pages should render with dark background and readable text.

- [ ] **Step 5: Commit**

```bash
git add resources/views/public/appointment-confirmed.blade.php resources/views/public/appointment-cancelled.blade.php resources/views/public/appointment-cancel.blade.php
git commit -m "feat: add dark mode to public standalone pages"
```
