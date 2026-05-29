<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · GestionalePro</title>
    <meta name="description" content="@yield('description', 'Software di gestione prenotazioni per saloni e centri estetici.')">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
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
    </style>
    @stack('styles')
</head>
<body class="bg-white text-gray-900 antialiased">

<header x-data="{ open: false, scrolled: false }"
        @scroll.window="scrolled = window.scrollY > 56"
        class="fixed top-0 inset-x-0 z-50 transition-all duration-300 bg-white/95 backdrop-blur-sm shadow-sm border-b border-gray-100">

    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="{{ url('/') }}" class="font-bold text-lg tracking-tight text-gray-900">
            Gestionale<span class="text-teal-600 font-normal">Pro</span>
        </a>

        <nav class="hidden md:flex items-center gap-8">
            <a href="{{ url('/') }}#funzionalita" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition">Funzionalità</a>
            <a href="{{ url('/') }}#prezzi" class="text-sm font-medium text-gray-500 hover:text-gray-900 transition">Prezzi</a>
            <a href="mailto:info@example.com"
               class="shimmer text-sm font-semibold bg-teal-600 text-white px-4 py-2 rounded-lg hover:bg-teal-700 transition shadow-sm">
                Inizia Gratis
            </a>
        </nav>

        <button @click="open = !open" class="md:hidden p-2 rounded-md text-gray-700">
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
         class="md:hidden bg-white border-b border-gray-100 shadow-lg">
        <div class="max-w-6xl mx-auto px-6 py-4 flex flex-col gap-1">
            <a href="{{ url('/') }}#funzionalita" @click="open = false"
               class="text-sm font-medium text-gray-700 px-2 py-3 rounded-lg hover:bg-slate-50 transition">Funzionalità</a>
            <a href="{{ url('/') }}#prezzi" @click="open = false"
               class="text-sm font-medium text-gray-700 px-2 py-3 rounded-lg hover:bg-slate-50 transition">Prezzi</a>
            <a href="mailto:info@example.com"
               class="mt-2 text-sm font-semibold bg-teal-600 text-white px-4 py-3 rounded-xl text-center hover:bg-teal-700 transition">
                Inizia Gratis
            </a>
        </div>
    </div>
</header>

<main class="pt-16">
    @yield('content')
</main>

<footer class="bg-slate-900 text-slate-400 py-16 px-6">
    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-10 mb-12">
            <div class="col-span-2 md:col-span-1">
                <div class="font-bold text-lg text-white tracking-tight mb-3">
                    Gestionale<span class="text-teal-400 font-normal">Pro</span>
                </div>
                <p class="text-sm leading-relaxed">Software di gestione per saloni, barbieri e centri estetici italiani.</p>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-widest mb-4">Prodotto</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ url('/') }}#funzionalita" class="hover:text-white transition">Funzionalità</a></li>
                    <li><a href="{{ url('/') }}#prezzi" class="hover:text-white transition">Prezzi</a></li>
                    <li><a href="{{ url('/') }}#come-funziona" class="hover:text-white transition">Come funziona</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-widest mb-4">Azienda</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="mailto:info@example.com" class="hover:text-white transition">Contatti</a></li>
                    <li><a href="mailto:info@example.com" class="hover:text-white transition">Supporto</a></li>
                    <li><a href="{{ route('legal.privacy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                    <li><a href="{{ route('legal.terms') }}" class="hover:text-white transition">Termini di servizio</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-800 pt-8 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-slate-500">
            <span>© {{ date('Y') }} GestionalePro. Tutti i diritti riservati.</span>
            <div class="flex gap-5">
                <a href="{{ route('legal.privacy') }}" class="hover:text-slate-300 transition">Privacy Policy</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-slate-300 transition">Termini di servizio</a>
            </div>
        </div>
    </div>
</footer>

@stack('scripts')
</body>
</html>
