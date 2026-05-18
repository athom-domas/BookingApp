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
