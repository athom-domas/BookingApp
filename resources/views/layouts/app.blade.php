<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php $salonProfile = \App\Models\SalonProfile::current(); @endphp

        <title>@yield('title', e($salonProfile->name)) - {{ $salonProfile->name }}</title>

        <style>
            :root { --color-primary: {{ preg_replace('/[^#0-9a-fA-F]/', '', $salonProfile->primary_color) }}; }
            .btn-primary { background-color: var(--color-primary) !important; }
            .btn-primary:hover { filter: brightness(0.9); }
            [x-cloak] { display: none !important; }
        </style>

        <script>
            if (localStorage.theme === 'dark' ||
                (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark')
            }
        </script>

        @fonts
        @filamentStyles
        @vite('resources/css/filament/admin/theme.css')
        @vite('resources/css/app.css')
        @stack('head')
    </head>
    <body class="min-h-screen bg-gray-50 dark:bg-gray-950 font-sans text-gray-950 dark:text-gray-50 antialiased">
        <header x-data="{ open: false }" class="border-b border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('booking.index') }}" class="flex min-w-0 items-center gap-3">
                    @if($salonProfile->logoUrl())
                        <img src="{{ $salonProfile->logoUrl() }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    @else
                        <img src="{{ asset('img/logo.png') }}" alt="" class="h-9 w-9 rounded-md object-contain">
                    @endif
                    <span class="truncate text-base font-semibold text-gray-950 dark:text-gray-50">{{ $salonProfile->name }}</span>
                </a>

                {{-- Desktop nav --}}
                <nav class="hidden sm:flex items-center gap-2 text-sm font-medium">
                    <a href="{{ route('booking.create') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Prenota</a>
                    @auth
                        <a href="{{ route('portal.appointments.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Appuntamenti</a>
                        <a href="{{ route('portal.settings.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Impostazioni</a>
                        @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                            <a href="{{ url('/admin') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Admin</a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Esci</button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Accedi</a>
                        <a href="{{ route('register') }}" class="btn-primary rounded-md px-3 py-2 text-white">Registrati</a>
                    @endauth

                    <button
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

                {{-- Mobile: dark mode + hamburger --}}
                <div class="flex sm:hidden items-center gap-1">
                    <button
                        @click="
                            document.documentElement.classList.toggle('dark');
                            localStorage.theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light'
                        "
                        class="rounded-md p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800"
                        aria-label="Cambia tema"
                    >
                        <svg class="hidden dark:block h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <svg class="block dark:hidden h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                    <button @click="open = !open" class="rounded-md p-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800" aria-label="Menu">
                        <svg x-show="!open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="open" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Mobile menu --}}
            <div x-show="open" x-cloak class="sm:hidden border-t border-gray-200 dark:border-gray-700 px-4 py-2 flex flex-col text-sm font-medium">
                <a href="{{ route('booking.create') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Prenota</a>
                @auth
                    <a href="{{ route('portal.appointments.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Appuntamenti</a>
                    <a href="{{ route('portal.settings.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Impostazioni</a>
                    @if (auth()->user()->isAdmin() || auth()->user()->isStaff())
                        <a href="{{ url('/admin') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Admin</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-left rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Esci</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Accedi</a>
                    <a href="{{ route('register') }}" class="btn-primary rounded-md px-3 py-2 text-white mt-1">Registrati</a>
                @endauth
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

        @if($salonProfile->phone || $salonProfile->address || $salonProfile->website)
        <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 mt-8">
            <div class="mx-auto max-w-6xl px-4 py-4 sm:px-6 lg:px-8 flex flex-wrap gap-4 text-sm text-gray-500 dark:text-gray-400">
                @if($salonProfile->phone)
                    <span>{{ $salonProfile->phone }}</span>
                @endif
                @if($salonProfile->address)
                    <span>{{ $salonProfile->address }}</span>
                @endif
                @if($salonProfile->website)
                    <a href="{{ $salonProfile->website }}" target="_blank" rel="noopener" class="hover:text-gray-700 dark:hover:text-gray-200">{{ $salonProfile->website }}</a>
                @endif
            </div>
        </footer>
        @endif

        @stack('scripts')
        @filamentScripts
        @vite('resources/js/app.js')
    </body>
</html>
