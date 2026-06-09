<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $salonProfile = \App\Models\SalonProfile::current();
            $_appFontUrls = [
                'classic' => 'https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=Inter:wght@300;400;500;600&display=swap',
                'modern'  => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap',
                'elegant' => 'https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;1,400&family=Nunito:wght@300;400;500;600&display=swap',
                'minimal' => 'https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;500;600;700&display=swap',
            ];
            $_appFontVars = [
                'classic' => ["'DM Serif Display', Georgia, serif", "'Inter', sans-serif"],
                'modern'  => ["'Plus Jakarta Sans', sans-serif",    "'Plus Jakarta Sans', sans-serif"],
                'elegant' => ["'Cormorant Garamond', Georgia, serif", "'Nunito', sans-serif"],
                'minimal' => ["'Space Grotesk', sans-serif",        "'Space Grotesk', sans-serif"],
            ];
            $_appRadiusMap = ['sharp' => '0', 'rounded' => '6px', 'pill' => '100px'];
            $_appPair        = $salonProfile->font_pair    ?? 'classic';
            $_appBorder      = $salonProfile->border_style ?? 'sharp';
            $_appFontUrl     = $_appFontUrls[$_appPair]    ?? $_appFontUrls['classic'];
            $_appFontDisplay = $_appFontVars[$_appPair][0] ?? $_appFontVars['classic'][0];
            $_appFontBody    = $_appFontVars[$_appPair][1] ?? $_appFontVars['classic'][1];
            $_appRadius      = $_appRadiusMap[$_appBorder] ?? '0';
            $_accentMap = [
                'luxury'     => ['light' => '#7a5c38', 'dark' => '#c9a96e'],
                'rosa'       => ['light' => '#9e4858', 'dark' => '#d4847a'],
                'verde'      => ['light' => '#4a7040', 'dark' => '#5eb870'],
                'notte'      => ['light' => '#3a50a0', 'dark' => '#7a96d4'],
                'minimal'    => ['light' => '#1a1a1a', 'dark' => '#d4d4d4'],
                'viola'      => ['light' => '#6a38a8', 'dark' => '#c090d8'],
                'terracotta' => ['light' => '#b85a20', 'dark' => '#d4784a'],
                'acqua'      => ['light' => '#1a8880', 'dark' => '#5adad0'],
                'cipria'     => ['light' => '#c87860', 'dark' => '#e09888'],
            ];
            $_appFamily  = $salonProfile->theme      ?? 'luxury';
            $_appMode    = $salonProfile->theme_mode ?? 'light';
            $_appPrimary = $_accentMap[$_appFamily][$_appMode] ?? '#7a5c38';
        @endphp

        <title>@yield('title', e($salonProfile->name)) - {{ $salonProfile->name }}</title>

        @if($salonProfile->faviconUrl())
            <link rel="icon" href="{{ $salonProfile->faviconUrl() }}">
        @endif

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link href="{{ $_appFontUrl }}" rel="stylesheet">
        <style>
            :root { --color-primary: {{ $_appPrimary }}; }
            .btn-primary { background-color: var(--color-primary) !important; }
            .btn-primary:hover { filter: brightness(0.9); }
            [x-cloak] { display: none !important; }
        </style>

        <script>
            (function() {
                var def  = @json($salonProfile->theme_mode ?? 'light');
                var mode = localStorage.getItem('sf-mode') || def;
                if (mode === 'dark') document.documentElement.classList.add('dark');
            })();
        </script>

        @fonts
        @filamentStyles
        @vite('resources/scss/filament/admin/theme.scss')
        @vite('resources/scss/app.scss')
        @stack('head')
        <style>
            :root { --sf-font-display: {{ $_appFontDisplay }}; --sf-font-body: {{ $_appFontBody }}; --sf-radius: {{ $_appRadius }}; }
            body { font-family: var(--sf-font-body), ui-sans-serif, system-ui, sans-serif; }
            .font-display, h1, h2, h3 { font-family: var(--sf-font-display); }
            .btn-primary { border-radius: var(--sf-radius) !important; }
            .rounded-md[href], button.rounded-md { border-radius: max(var(--sf-radius), 6px); }
            .sf-accent-link { color: var(--color-primary); }
            html.dark .sf-accent-link { color: color-mix(in srgb, var(--color-primary) 60%, white 40%); }
        </style>
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
                        @if (\App\Models\Product::inSale()->exists())
                            <a href="{{ route('portal.products.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Prodotti</a>
                        @endif
                        @if (\App\Models\ProductOrder::where('user_id', auth()->id())->exists())
                            <a href="{{ route('portal.orders.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 hover:text-gray-950 dark:hover:text-gray-50">Ordini</a>
                        @endif
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
                            localStorage.setItem('sf-mode', document.documentElement.classList.contains('dark') ? 'dark' : 'light')
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
                            localStorage.setItem('sf-mode', document.documentElement.classList.contains('dark') ? 'dark' : 'light')
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
                    @if (\App\Models\Product::inSale()->exists())
                        <a href="{{ route('portal.products.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Prodotti</a>
                    @endif
                    @if (\App\Models\ProductOrder::where('user_id', auth()->id())->exists())
                        <a href="{{ route('portal.orders.index') }}" class="rounded-md px-3 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800">Ordini</a>
                    @endif
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

        <main class="@yield('main-class', 'mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8')">
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

        <footer class="border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 py-8 px-4">
            <div class="mx-auto max-w-6xl space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        @if($salonProfile->logoUrl())
                            <img src="{{ $salonProfile->logoUrl() }}" class="h-7 object-contain" alt="{{ $salonProfile->name }}">
                        @endif
                        <span class="text-sm font-semibold text-gray-900 dark:text-gray-50">{{ $salonProfile->name }}</span>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                        @if($salonProfile->phone)
                            <span>{{ $salonProfile->phone }}</span>
                        @endif
                        @if($salonProfile->address)
                            <span>{{ $salonProfile->address }}</span>
                        @endif
                    </div>
                    <div class="flex items-center gap-4">
                        @if($salonProfile->instagram_url)
                            <a href="{{ $salonProfile->instagram_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Instagram">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                            </a>
                        @endif
                        @if($salonProfile->facebook_url)
                            <a href="{{ $salonProfile->facebook_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="Facebook">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </a>
                        @endif
                        @if($salonProfile->tiktok_url)
                            <a href="{{ $salonProfile->tiktok_url }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="TikTok">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.33 6.33 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.73a4.85 4.85 0 01-1.01-.04z"/></svg>
                            </a>
                        @endif
                        @if($salonProfile->whatsapp_number)
                            <a href="https://wa.me/{{ $salonProfile->whatsapp_number }}" target="_blank" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300" aria-label="WhatsApp">
                                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M11.999 0C5.373 0 0 5.373 0 12c0 2.117.554 4.107 1.523 5.832L.044 23.956l6.278-1.647A11.944 11.944 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 11.999 0zm.001 21.818a9.818 9.818 0 01-5.011-1.37l-.36-.213-3.726.977.997-3.634-.234-.374A9.775 9.775 0 012.182 12C2.182 6.57 6.57 2.182 12 2.182S21.818 6.57 21.818 12 17.43 21.818 12 21.818z"/></svg>
                            </a>
                        @endif
                    </div>
                </div>
                <p class="text-center text-xs text-gray-400">
                    © {{ date('Y') }} {{ $salonProfile->name }}. Tutti i diritti riservati.
                </p>
            </div>
        </footer>

        @stack('scripts')
        @vite('resources/js/app.js')
    </body>
</html>
