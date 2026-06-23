<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestionalePro · Software prenotazioni per saloni e centri estetici</title>
    <meta name="description" content="Gestisci prenotazioni, staff e pagamenti del tuo salone in un'unica piattaforma. 14 giorni gratis, nessuna carta richiesta.">
    <meta name="google-site-verification" content="nD-pGjHxVgpI6sDbYgST9j3ThrJgVLeJgX-qkYDrbcs" />
    <script>if(location.hash)history.replaceState(null,'',location.pathname)</script>
    @vite(['resources/css/app.css', 'resources/scss/landing.scss', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
</head>
<body class="bg-cream text-ink antialiased">

<div id="spb"></div>

{{-- NAV --}}
<header x-data="{ open: false, scrolled: false }"
        @scroll.window="scrolled = window.scrollY > 56"
        class="fixed top-0 inset-x-0 z-50 transition-all duration-300"
        :class="scrolled ? 'bg-cream/95 backdrop-blur-sm shadow-sm border-b border-warm-border' : 'bg-transparent'">

    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <a href="{{ url('/') }}" class="font-bold text-lg tracking-tight transition-colors"
           :class="scrolled ? 'text-ink' : 'text-white'">
            Gestionale<span class="font-normal text-terra">Pro</span>
        </a>

        <nav class="hidden md:flex items-center gap-8">
            <a href="#funzionalita" @click.prevent="scrollToSection('funzionalita')" class="text-sm font-medium transition"
               :class="scrolled ? 'text-ink-muted hover:text-ink' : 'text-white/80 hover:text-white'">Funzionalità</a>
            <a href="#prezzi" @click.prevent="scrollToSection('prezzi')" class="text-sm font-medium transition"
               :class="scrolled ? 'text-ink-muted hover:text-ink' : 'text-white/80 hover:text-white'">Prezzi</a>
            <a href="{{ route('contact') }}"
               class="shimmer text-sm font-semibold bg-terra text-white px-4 py-2 rounded-lg hover:bg-terra/90 transition shadow-sm">
                Inizia Gratis
            </a>
        </nav>

        <button @click="open = !open" class="md:hidden p-2 rounded-md transition relative w-9 h-9 flex items-center justify-center"
                :class="scrolled ? 'text-ink' : 'text-white'">
            <svg x-show="!open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 rotate-90"
                 x-transition:enter-end="opacity-100 rotate-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 rotate-0"
                 x-transition:leave-end="opacity-0 -rotate-90"
                 class="w-5 h-5 absolute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
            <svg x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -rotate-90"
                 x-transition:enter-end="opacity-100 rotate-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 rotate-0"
                 x-transition:leave-end="opacity-0 rotate-90"
                 class="w-5 h-5 absolute" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div class="md:hidden nav-drawer"
         :style="open ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
        <div class="overflow-hidden">
            <div class="nav-drawer-content bg-cream border-b border-warm-border shadow-lg"
                 :class="open ? 'opacity-100' : 'opacity-0'">
                <div class="max-w-6xl mx-auto px-6 py-4 flex flex-col gap-1">
                    <a href="#funzionalita" @click.prevent="scrollToSection('funzionalita'); open = false"
                       class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Funzionalità</a>
                    <a href="#prezzi" @click.prevent="scrollToSection('prezzi'); open = false"
                       class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Prezzi</a>
                    <a href="{{ route('contact') }}"
                       class="mt-2 text-sm font-semibold bg-terra text-white px-4 py-3 rounded-xl text-center hover:bg-terra/90 transition">
                        Inizia Gratis
                    </a>
                </div>
            </div>
        </div>
    </div>
</header>


{{-- HERO --}}
<section class="bg-ink relative overflow-hidden min-h-[680px] flex items-center pt-16 pb-24 px-6">

    <div class="relative z-10 max-w-4xl mx-auto text-center">
        <div class="h-badge inline-flex items-center gap-2 bg-terra/15 border border-terra/25 rounded-full px-4 py-1.5 mb-8">
            <span class="w-1.5 h-1.5 rounded-full bg-terra animate-pulse"></span>
            <span class="text-xs font-medium text-terra-light tracking-wide">Software gestionale per saloni</span>
        </div>

        <h1 class="h-title font-display text-6xl sm:text-7xl lg:text-8xl font-semibold text-white leading-[1.05] mb-6 text-balance">
            Porta il tuo salone a un livello più
            <span class="text-terra"> professionale</span>
        </h1>

        <p class="h-sub text-lg sm:text-xl text-white/70 max-w-2xl mx-auto mb-10 leading-relaxed">
Offri prenotazioni online, pagamenti digitali, promemoria automatici e una gestione completa dello staff, senza complicare il lavoro quotidiano.
        </p>

        <div class="h-ctas flex flex-col sm:flex-row items-center justify-center gap-4">
            <a href="{{ route('contact') }}"
               class="shimmer w-full sm:w-auto inline-flex items-center justify-center gap-2 bg-terra hover:bg-terra/85 text-white font-semibold px-8 py-4 rounded-xl transition text-base shadow-lg shadow-ink/30">
                Richiedi una Demo Gratuita
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
            <a href="#come-funziona" @click.prevent="scrollToSection('come-funziona')"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-white/70 hover:text-white transition">
                Scopri come funziona
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </div>

        <p class="h-fine mt-6 text-xs text-white/50">
            Nessuna carta di credito richiesta &middot; 14 giorni gratuiti &middot; Cancelli quando vuoi
        </p>
    </div>

</section>


{{-- TRUST BAR --}}
<section class="bg-cream-dark border-b border-warm-border py-8 px-6">
    <div class="max-w-4xl mx-auto">
        <div class="grid grid-cols-2 sm:flex sm:items-center sm:justify-center gap-6 sm:gap-0 text-center">
            <div class="flex flex-col items-center gap-1.5 sm:flex-1 sm:px-5" data-r style="--d:0">
                <span class="font-display text-[clamp(2rem,4vw,3rem)] font-semibold leading-none text-terra" data-counter="500" data-suffix="+">500+</span>
                <span class="text-xs font-medium text-ink-muted leading-snug">Saloni attivi</span>
            </div>
            <div class="hidden sm:block w-px h-9 bg-warm-border shrink-0" aria-hidden="true"></div>
            <div class="flex flex-col items-center gap-1.5 sm:flex-1 sm:px-5" data-r style="--d:1">
                <span class="font-display text-[clamp(2rem,4vw,3rem)] font-semibold leading-none text-terra" data-counter="100" data-suffix="k+">100k+</span>
                <span class="text-xs font-medium text-ink-muted leading-snug">Clienti gestiti al mese</span>
            </div>
            <div class="hidden sm:block w-px h-9 bg-warm-border shrink-0" aria-hidden="true"></div>
            <div class="flex flex-col items-center gap-1.5 sm:flex-1 sm:px-5" data-r style="--d:2">
                <span class="font-display text-[clamp(2rem,4vw,3rem)] font-semibold leading-none text-terra">7/7</span>
                <span class="text-xs font-medium text-ink-muted leading-snug">Giorni di supporto</span>
            </div>
            <div class="hidden sm:block w-px h-9 bg-warm-border shrink-0" aria-hidden="true"></div>
            <div class="flex flex-col items-center gap-1.5 sm:flex-1 sm:px-5" data-r style="--d:3">
                <span class="font-display text-[clamp(2rem,4vw,3rem)] font-semibold leading-none text-terra">GDPR</span>
                <span class="text-xs font-medium text-ink-muted leading-snug">Compliant &amp; Pagamenti sicuri</span>
            </div>
        </div>
    </div>
</section>


{{-- PROBLEM --}}
<section class="py-24 px-6 bg-cream">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">
                Ancora usi carta, WhatsApp<br class="hidden sm:block"> e fogli Excel per gestire il salone?
            </h2>
            <p class="text-ink-muted max-w-xl mx-auto" data-r style="--d:1">
                Ogni giorno perdi ore preziose su problemi che si risolvono in automatico.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            <div class="bg-cream-dark rounded-2xl p-8 border border-warm-border" data-r style="--d:0">
                <div class="font-display text-[3.5rem] font-semibold italic text-terra leading-none mb-4">01</div>
                <h3 class="font-semibold text-ink mb-2">Clienti che si dimenticano</h3>
                <p class="text-sm text-ink-muted leading-relaxed">Perdi tempo a richiamare e inviare messaggi a mano. I no-show ti costano soldi ogni settimana.</p>
            </div>

            <div class="bg-cream-dark rounded-2xl p-8 border border-warm-border" data-r style="--d:1">
                <div class="font-display text-[3.5rem] font-semibold italic text-terra leading-none mb-4">02</div>
                <h3 class="font-semibold text-ink mb-2">Incassi difficili da tracciare</h3>
                <p class="text-sm text-ink-muted leading-relaxed">Contanti, carte, bonifici: a fine mese non sai mai quanto hai incassato davvero e dove.</p>
            </div>

            <div class="bg-cream-dark rounded-2xl p-8 border border-warm-border" data-r style="--d:2">
                <div class="font-display text-[3.5rem] font-semibold italic text-terra leading-none mb-4">03</div>
                <h3 class="font-semibold text-ink mb-2">Staff e turni da coordinare</h3>
                <p class="text-sm text-ink-muted leading-relaxed">Ogni operatore con orari diversi. Senza un sistema unico, le sovrapposizioni sono inevitabili.</p>
            </div>

        </div>
    </div>
</section>


{{-- FEATURES --}}
<section id="funzionalita" class="py-24 px-6 bg-cream-dark scroll-mt-16">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-20">
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">Tutto quello che serve per gestire il tuo salone</h2>
            <p class="text-ink-muted max-w-xl mx-auto" data-r style="--d:1">Una piattaforma completa, progettata per farti risparmiare tempo ogni giorno.</p>
        </div>
        <div class="flex flex-col gap-10" data-r style="--d:2">
            {{-- Pair 1 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Prenotazioni Online 24/7</p>
                    <p class="text-sm text-ink-muted leading-relaxed">I clienti prenotano dal telefono in qualsiasi momento. Nessuna telefonata, nessun messaggio da gestire.</p>
                </div>
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Gestione Staff e Turni</p>
                    <p class="text-sm text-ink-muted leading-relaxed">Ogni operatore con il proprio calendario, servizi assegnati e disponibilità personalizzata.</p>
                </div>
            </div>
            <hr class="border-t border-warm-border">
            {{-- Pair 2 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Pagamenti Integrati</p>
                    <p class="text-sm text-ink-muted leading-relaxed">Stripe, POS o contanti: incassa online o in salone. Ogni transazione tracciata in automatico.</p>
                </div>
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Promemoria Automatici</p>
                    <p class="text-sm text-ink-muted leading-relaxed">Email, SMS e WhatsApp prima dell'appuntamento. I no-show si riducono drasticamente.</p>
                </div>
            </div>
            <hr class="border-t border-warm-border">
            {{-- Pair 3 --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 md:gap-12">
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Lista d'Attesa Intelligente</p>
                    <p class="text-sm text-ink-muted leading-relaxed">Slot liberati? Il sistema avvisa i clienti in attesa e gestisce le sostituzioni in autonomia.</p>
                </div>
                <div>
                    <div class="w-10 h-10 rounded-full bg-terra-light flex items-center justify-center mb-4">
                        <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#C4714A" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    </div>
                    <p class="font-semibold text-ink mb-1.5">Report e Statistiche</p>
                    <p class="text-sm text-ink-muted leading-relaxed">Incassi, servizi più richiesti e performance dello staff in tempo reale, sempre aggiornati.</p>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- HOW IT WORKS --}}
<section id="come-funziona" class="py-24 px-6 bg-cream scroll-mt-16">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">
                Attivo in 5 minuti. Nessuna installazione.
            </h2>
            <p class="text-ink-muted" data-r style="--d:1">Bastano tre passi per portare il tuo salone online.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-6 relative">
            {{-- Connettore tratteggiato desktop --}}
            <div class="hidden md:block absolute top-7 left-[calc(16.7%+1.5rem)] right-[calc(16.7%+1.5rem)] h-px"
                 style="background:repeating-linear-gradient(to right,#C4714A 0,#C4714A 6px,transparent 6px,transparent 14px)"></div>

            @foreach([
                ['n'=>'01','title'=>'Registra il tuo salone',      'desc'=>'Crea il profilo, aggiungi i servizi e configura gli orari in meno di 5 minuti.'],
                ['n'=>'02','title'=>'Condividi il link',            'desc'=>'I clienti prenotano dalla tua pagina dedicata, direttamente dallo smartphone, h24.'],
                ['n'=>'03','title'=>'Gestisci tutto dal pannello',  'desc'=>'Appuntamenti, pagamenti, staff e statistiche in un\'unica schermata sempre aggiornata.'],
            ] as $i => $s)
            <div class="flex flex-col items-center text-center relative" data-r style="--d:{{ $i + 2 }}">
                <div class="w-14 h-14 rounded-full bg-terra text-white flex items-center justify-center text-lg font-bold mb-5 shadow-lg shadow-terra/20 relative z-10">
                    {{ $s['n'] }}
                </div>
                <h3 class="text-lg font-semibold text-ink mb-2">{{ $s['title'] }}</h3>
                <p class="text-sm text-ink-muted leading-relaxed max-w-xs">{{ $s['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- VIDEO DEMO --}}
<section class="py-24 px-6 bg-cream-dark">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-xs font-semibold text-terra uppercase tracking-widest mb-3" data-r style="--d:0">Demo</p>
        <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:1">
            Guardalo in azione
        </h2>
        <p class="text-ink-muted mb-10" data-r style="--d:2">Da zero a prima prenotazione: meno di 2 minuti.</p>
        <div class="rounded-2xl overflow-hidden shadow-2xl border border-warm-border" data-r style="--d:3">
            <video controls
                   poster="/img/screenshots/screenshot-lista.png"
                   class="w-full block"
                   preload="none">
                <source src="/video/demo.mp4" type="video/mp4">
            </video>
        </div>
    </div>
</section>


{{-- PRODUCT PREVIEW --}}
<section class="py-24 px-6 bg-ink">
    <div class="max-w-6xl mx-auto">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 lg:gap-20 items-center">

            {{-- Left: copy --}}
            <div data-r style="--d:0">
                <h2 class="font-display text-4xl sm:text-5xl font-semibold text-white mb-6 text-balance">
                    Il pannello che semplifica ogni giornata
                </h2>
                <p class="text-white/70 text-lg leading-relaxed mb-8 text-pretty">
                    Appuntamenti, staff, incassi e lista d'attesa in una sola schermata.
                    Aggiornato in tempo reale, accessibile da qualsiasi dispositivo.
                </p>
                <ul class="space-y-4">
                    @foreach([
                        'Calendario operatori con disponibilità in tempo reale',
                        'Stato di ogni appuntamento, aggiornato automaticamente',
                        'Notifiche istantanee per nuove prenotazioni e disdette',
                    ] as $item)
                    <li class="flex items-start gap-3 text-white/70 text-sm leading-relaxed">
                        <svg class="w-5 h-5 text-terra shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $item }}
                    </li>
                    @endforeach
                </ul>
            </div>

            {{-- Right: screenshot tab switcher --}}
            <div data-r style="--d:1" x-data="{ tab: 'lista' }">
                <div class="rounded-xl overflow-hidden shadow-2xl shadow-black/60 border border-white/10">

                    {{-- Chrome bar --}}
                    <div class="flex items-center gap-3 px-4 py-2.5" style="background:#1a1b26">
                        <div class="flex gap-1.5 shrink-0">
                            <div class="w-3 h-3 rounded-full" style="background:#ff5f57"></div>
                            <div class="w-3 h-3 rounded-full" style="background:#febc2e"></div>
                            <div class="w-3 h-3 rounded-full" style="background:#28c840"></div>
                        </div>
                        <div class="flex-1 rounded px-3 py-1 text-xs font-mono" style="background:#2a2b3d;color:rgba(255,255,255,0.3)">
                            gestionale.pro/admin
                        </div>
                    </div>

                    {{-- Tab bar --}}
                    <div class="flex" style="background:#1a1b26;border-bottom:1px solid rgba(255,255,255,0.08)">
                        <button @click="tab = 'lista'"
                                class="px-4 py-2.5 text-xs font-medium transition-colors border-b-2"
                                :class="tab === 'lista' ? 'text-terra border-terra' : 'text-white/40 border-transparent hover:text-white/70'">
                            Lista appuntamenti
                        </button>
                        <button @click="tab = 'calendario'"
                                class="px-4 py-2.5 text-xs font-medium transition-colors border-b-2"
                                :class="tab === 'calendario' ? 'text-terra border-terra' : 'text-white/40 border-transparent hover:text-white/70'">
                            Calendario
                        </button>
                    </div>

                    {{-- Screenshots --}}
                    <div style="height:360px" class="overflow-hidden bg-cream">
                        <img x-show="tab === 'lista'"
                             src="/img/screenshots/screenshot-lista.png"
                             alt="Lista appuntamenti nel pannello admin"
                             class="w-full h-full object-cover object-top">
                        <img x-show="tab === 'calendario'"
                             src="/img/screenshots/screenshot-calendario.png"
                             alt="Vista calendario con drag-and-drop"
                             class="w-full h-full object-cover object-top">
                    </div>

                </div>
            </div>

        </div>
    </div>
</section>


{{-- PRICING --}}
<section id="prezzi" class="py-24 px-6 bg-cream-dark scroll-mt-16">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">Prezzo semplice e trasparente</h2>
        <p class="text-ink-muted mb-2" data-r style="--d:1">Nessun costo di setup. Cancelli quando vuoi. I primi 14 giorni sono gratis.</p>
        <p class="text-xs text-ink-muted/60 mb-12" data-r style="--d:2">Prezzi IVA esclusa</p>

        <div class="max-w-sm mx-auto" data-r style="--d:2">
            <div class="bg-cream rounded-2xl p-8 border border-warm-border shadow-xl shadow-ink/10 flex flex-col">
                <div class="mb-6 text-left">
                    <h3 class="font-bold text-ink text-lg mb-1">Piano completo</h3>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="font-display text-6xl font-semibold text-ink">€29</span>
                        <span class="text-ink-muted text-sm mb-2">/mese</span>
                    </div>
                    <p class="text-xs text-ink-muted mt-1.5">Per saloni di ogni dimensione</p>
                </div>
                <ul class="space-y-3 mb-8 flex-1 text-left">
                    @foreach(['Operatori illimitati','Prenotazioni online illimitate','Promemoria email automatici','Pagamenti online e in salone','Lista d\'attesa intelligente','Report e statistiche','Supporto 7 giorni su 7'] as $feat)
                    <li class="flex items-center gap-2.5 text-sm text-ink-muted">
                        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('contact') }}"
                   class="shimmer block w-full text-center text-sm font-semibold bg-terra text-white py-3.5 rounded-xl hover:bg-terra/90 transition">
                    Inizia i 14 Giorni Gratis
                </a>
            </div>
        </div>
    </div>
</section>


{{-- TESTIMONIALS --}}
<section class="py-24 px-6 bg-cream">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">Cosa dicono i nostri clienti</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['quote'=>'Prima passavo mezz\'ora ogni mattina a confermare appuntamenti su WhatsApp. Adesso arrivano i clienti e basta. I promemoria pensano a tutto, io non devo fare niente.','name'=>'Giulia Rossi',  'role'=>'Parrucchiera, Milano','initial'=>'G','color'=>'bg-terra'],
                ['quote'=>'Ho tre colleghi in salone e prima era il caos: turni sbagliati, pagamenti da registrare a mano. Adesso tutto è in ordine e so sempre com\'è andata la settimana.',    'name'=>'Marco Torrisi', 'role'=>'Barbiere, Roma',       'initial'=>'M','color'=>'bg-indigo-500'],
                ['quote'=>'Le mie clienti prenotano quando vogliono, anche a mezzanotte. Non rispondo più a nessun messaggio per gli appuntamenti. E le prenotazioni sono aumentate.',              'name'=>'Alessia Marino','role'=>'Estetista, Torino',    'initial'=>'A','color'=>'bg-rose-500'],
            ] as $i => $t)
            <article class="bg-cream-dark rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">
                <div class="font-display text-6xl text-terra/30 leading-none mb-1 select-none">&ldquo;</div>
                <div class="flex gap-0.5 mb-5">
                    @for($s = 0; $s < 5; $s++)
                    <svg class="w-4 h-4 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @endfor
                </div>
                <p class="text-sm text-ink-muted leading-relaxed mb-6 flex-1">"{{ $t['quote'] }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full {{ $t['color'] }} flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ $t['initial'] }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-ink">{{ $t['name'] }}</p>
                        <p class="text-xs text-ink-muted">{{ $t['role'] }}</p>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>


{{-- FAQ --}}
<section class="py-24 px-6 bg-cream-dark">
    <div class="max-w-2xl mx-auto" x-data="{ active: null }">
        <div class="text-center mb-16">
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4 text-balance" data-r style="--d:0">Domande frequenti</h2>
        </div>
        <div class="space-y-2">
            @foreach([
                ['q'=>'Quanto costa davvero?',                    'a'=>'Il piano è €29/mese, senza costi nascosti o commissioni sulle prenotazioni. I primi 14 giorni sono completamente gratuiti, nessuna carta di credito richiesta.'],
                ['q'=>'Devo installare qualcosa?',                'a'=>'No. È tutto basato sul web. Accedi da qualsiasi browser su computer, tablet o smartphone. Nessuna installazione, nessun aggiornamento manuale.'],
                ['q'=>'Posso importare i miei clienti esistenti?','a'=>'Sì. Puoi importare clienti e storico prenotazioni tramite file CSV o aggiungerli manualmente. Il nostro team ti supporta durante la migrazione.'],
                ['q'=>"C'è supporto in italiano?",                'a'=>'Sì. Il supporto è completamente in italiano, disponibile via email e chat 7 giorni su 7. Il tempo medio di risposta è sotto le 2 ore.'],
                ['q'=>'Posso cancellare quando voglio?',          'a'=>'Assolutamente. Nessun vincolo contrattuale, nessuna penale. Cancelli con un click dalla dashboard e non ti viene addebitato nulla dal mese successivo.'],
            ] as $idx => $faq)
            <div class="bg-cream rounded-xl border border-warm-border overflow-hidden" data-r style="--d:{{ $idx }}">
                <button @click="active === {{ $idx }} ? active = null : active = {{ $idx }}"
                        class="w-full flex items-center justify-between px-6 py-5 text-left font-medium text-ink hover:bg-cream-dark transition-colors text-sm">
                    <span>{{ $faq['q'] }}</span>
                    <svg class="w-4 h-4 text-ink-muted shrink-0 transition-transform duration-200"
                         :class="active === {{ $idx }} ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div class="faq-body grid"
                     :style="active === {{ $idx }} ? 'grid-template-rows: 1fr' : 'grid-template-rows: 0fr'">
                    <div class="overflow-hidden">
                        <div class="px-6 pb-5 pt-4 text-sm text-ink-muted leading-relaxed border-t border-warm-border">
                            {{ $faq['a'] }}
                        </div>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- CTA FINALE --}}
<section class="bg-ink relative overflow-hidden py-24 px-6 text-center">
    <div class="relative z-10 max-w-2xl mx-auto">
        <h2 class="font-display text-4xl sm:text-5xl font-semibold text-white mb-4 text-balance" data-r style="--d:0">
            Il tuo salone merita di più. Inizia oggi.
        </h2>
        <p class="text-white/70 mb-8 text-lg leading-relaxed" data-r style="--d:1">
            Unisciti a 500+ saloni che hanno già scelto GestionalePro.
        </p>
        <div data-r style="--d:2">
            <a href="{{ route('contact') }}"
               class="shimmer inline-flex items-center gap-2 bg-terra hover:bg-terra/85 text-white font-semibold px-8 py-4 rounded-xl transition text-base shadow-lg shadow-ink/30">
                Inizia i 14 Giorni Gratis
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
            <p class="mt-4 text-xs text-white/50">Nessuna carta di credito &middot; Cancelli quando vuoi</p>
        </div>
    </div>
</section>


{{-- FOOTER --}}
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
                    <li><a href="#funzionalita" @click.prevent="scrollToSection('funzionalita')" class="hover:text-white transition">Funzionalità</a></li>
                    <li><a href="#prezzi" @click.prevent="scrollToSection('prezzi')" class="hover:text-white transition">Prezzi</a></li>
                    <li><a href="#come-funziona" @click.prevent="scrollToSection('come-funziona')" class="hover:text-white transition">Come funziona</a></li>
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

<script>
(function () {
    // Barra progresso scroll
    var bar = document.getElementById('spb');
    window.addEventListener('scroll', function () {
        bar.style.transform = 'scaleX(' + (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) + ')';
    }, { passive: true });

    // Scroll reveal
    var ro = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            e.target.classList.add('on');
            ro.unobserve(e.target);
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('[data-r]').forEach(function (el) { ro.observe(el); });

    // Counter animato
    var co = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
            if (!e.isIntersecting) return;
            var el = e.target;
            var target = parseInt(el.dataset.counter, 10);
            var suffix = el.dataset.suffix || '';
            var t0 = performance.now();
            (function tick(now) {
                var p = Math.min((now - t0) / 1400, 1);
                var eased = 1 - Math.pow(1 - p, 3);
                el.textContent = Math.round(eased * target) + suffix;
                if (p < 1) requestAnimationFrame(tick);
            })(t0);
            co.unobserve(el);
        });
    }, { threshold: 0.5 });
    document.querySelectorAll('[data-counter]').forEach(function (el) { co.observe(el); });

    // Spotlight sulle feature card
    document.querySelectorAll('.f-card').forEach(function (card) {
        card.addEventListener('mousemove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
            card.style.setProperty('--my', (e.clientY - r.top) + 'px');
        });
    });
})();
</script>

</body>
</html>
