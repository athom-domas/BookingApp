<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · GestionalePro</title>
    <meta name="description" content="@yield('description', 'Software di gestione prenotazioni per saloni e centri estetici.')">
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }
        html { scroll-padding-top: 80px; }
        .shimmer { position: relative; overflow: hidden; }
        .shimmer::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.22) 50%, transparent 70%);
            transform: translateX(-100%) skewX(-15deg);
        }
        .shimmer:hover::after { animation: shine 0.6s ease forwards; }
        @keyframes shine { to { transform: translateX(150%) skewX(-15deg); } }
        .font-display { font-family: 'Cormorant Garamond', Georgia, serif; }
    </style>
    @stack('styles')
</head>
<body class="bg-cream text-ink antialiased">

<header x-data="{ open: false, scrolled: false }"
        @scroll.window="scrolled = window.scrollY > 56"
        class="fixed top-0 inset-x-0 z-50 transition-all duration-300 bg-cream/95 backdrop-blur-sm shadow-sm border-b border-warm-border">

    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="{{ url('/') }}" class="font-bold text-lg tracking-tight text-ink">
            Gestionale<span class="text-terra font-normal">Pro</span>
        </a>

        <nav class="hidden md:flex items-center gap-8">
            <a href="{{ url('/') }}#funzionalita" class="text-sm font-medium text-ink-muted hover:text-ink transition">Funzionalità</a>
            <a href="{{ url('/') }}#prezzi" class="text-sm font-medium text-ink-muted hover:text-ink transition">Prezzi</a>
            <a href="{{ route('contact') }}"
               class="shimmer text-sm font-semibold bg-terra text-white px-4 py-2 rounded-lg hover:bg-terra/90 transition shadow-sm">
                Inizia Gratis
            </a>
        </nav>

        <button @click="open = !open" class="md:hidden p-2 rounded-md text-ink">
            <svg x-show="!open" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="open" x-cloak class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div x-show="open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="-translate-y-2"
         x-transition:enter-end="translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="translate-y-0"
         x-transition:leave-end="-translate-y-2"
         class="md:hidden bg-cream border-b border-warm-border shadow-lg">
        <div class="max-w-6xl mx-auto px-6 py-4 flex flex-col gap-1">
            <a href="{{ url('/') }}#funzionalita" @click="open = false"
               class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Funzionalità</a>
            <a href="{{ url('/') }}#prezzi" @click="open = false"
               class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Prezzi</a>
            <a href="{{ route('contact') }}"
               class="mt-2 text-sm font-semibold bg-terra text-white px-4 py-3 rounded-xl text-center hover:bg-terra/90 transition">
                Inizia Gratis
            </a>
        </div>
    </div>
</header>

<main class="pt-16">
    @yield('content')
</main>

<footer class="bg-ink text-ink-muted py-16 px-6">
    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-10 mb-12">
            <div class="col-span-2 md:col-span-1">
                <div class="font-bold text-lg text-white tracking-tight mb-3">
                    Gestionale<span class="text-terra font-normal">Pro</span>
                </div>
                <p class="text-sm leading-relaxed">Software di gestione per saloni, barbieri e centri estetici italiani.</p>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-white/70 uppercase tracking-widest mb-4">Prodotto</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/') }}#funzionalita" class="hover:text-white transition">Funzionalità</a></li>
                    <li><a href="{{ url('/') }}#prezzi" class="hover:text-white transition">Prezzi</a></li>
                    <li><a href="{{ url('/') }}#come-funziona" class="hover:text-white transition">Come funziona</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-white/70 uppercase tracking-widest mb-4">Azienda</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contatti</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Supporto</a></li>
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-white transition">Termini di servizio</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-white/10 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-ink-muted/70">
            <span>© {{ date('Y') }} GestionalePro. Tutti i diritti riservati.</span>
            <div class="flex gap-5">
                <a href="{{ route('legal.privacy') }}" class="hover:text-white/90 transition">Privacy Policy</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-white/90 transition">Termini di servizio</a>
            </div>
        </div>
    </div>
</footer>

@stack('scripts')
</body>
</html>
