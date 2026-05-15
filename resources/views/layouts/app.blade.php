<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Booking App') - Booking App</title>

        @fonts
        @filamentStyles
        @vite('resources/css/app.css')
        @vite('resources/css/filament/admin/theme.css')
        @stack('head')
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-950 antialiased">
        <header class="border-b border-gray-200 bg-white">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('booking.index') }}" class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    <span class="truncate text-base font-semibold text-gray-950">Booking App</span>
                </a>

                <nav class="flex items-center gap-2 text-sm font-medium">
                    <a href="{{ route('booking.create') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-950">Prenota</a>
                    @auth
                        <a href="{{ route('portal.appointments.index') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-950">Appuntamenti</a>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <a href="{{ url('/admin') }}" class="hidden rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-950 sm:inline-flex">Admin</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-950">Esci</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-gray-950">Accedi</a>
                        <a href="{{ route('register') }}" class="rounded-md bg-blue-700 px-3 py-2 text-white hover:bg-blue-800">Registrati</a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-6 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
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
