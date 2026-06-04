<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GestionalePro · Software prenotazioni per saloni e centri estetici</title>
    <meta name="description" content="Gestisci prenotazioni, staff e pagamenti del tuo salone in un'unica piattaforma. 14 giorni gratis, nessuna carta richiesta.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        [x-cloak] { display: none !important; }

        /* ── Entrata hero (auto-play) ── */
        @keyframes fadeUp   { from { opacity:0; transform:translateY(26px); } to { opacity:1; transform:translateY(0); } }
        @keyframes fadeDown { from { opacity:0; transform:translateY(-14px); } to { opacity:1; transform:translateY(0); } }
        .h-badge { animation: fadeDown 0.7s cubic-bezier(.22,1,.36,1) 0.10s both; }
        .h-title { animation: fadeUp  0.8s cubic-bezier(.22,1,.36,1) 0.25s both; }
        .h-sub   { animation: fadeUp  0.8s cubic-bezier(.22,1,.36,1) 0.40s both; }
        .h-ctas  { animation: fadeUp  0.8s cubic-bezier(.22,1,.36,1) 0.55s both; }
        .h-fine  { animation: fadeUp  0.6s ease                       0.72s both; }

        /* ── Scroll reveal ── */
        [data-r] {
            opacity: 0;
            transform: translateY(22px);
            transition: opacity 0.65s ease, transform 0.65s ease;
            transition-delay: calc(var(--d, 0) * 110ms);
        }
        [data-r].on { opacity: 1; transform: translateY(0); }

        /* ── Feature card spotlight ── */
        .f-card { position: relative; isolation: isolate; }
        .f-card::before {
            content: '';
            position: absolute; inset: 0;
            border-radius: inherit;
            opacity: 0;
            transition: opacity 0.35s;
            background: radial-gradient(500px circle at var(--mx,50%) var(--my,50%), rgba(196,113,74,0.09), transparent 65%);
            pointer-events: none;
        }
        .f-card:hover::before { opacity: 1; }
        .f-card > * { position: relative; z-index: 1; }

        /* ── Shimmer su bottoni ── */
        .shimmer { position: relative; overflow: hidden; }
        .shimmer::after {
            content: '';
            position: absolute; inset: 0;
            background: linear-gradient(110deg, transparent 30%, rgba(255,255,255,0.22) 50%, transparent 70%);
            transform: translateX(-100%) skewX(-15deg);
        }
        .shimmer:hover::after { animation: shine 0.6s ease forwards; }
        @keyframes shine { to { transform: translateX(150%) skewX(-15deg); } }

        /* ── Barra progresso scroll ── */
        #spb {
            position: fixed; top: 0; left: 0;
            height: 2px; width: 0%;
            background: linear-gradient(to right, #C4714A, #1C1410);
            z-index: 200;
            transition: width 0.08s linear;
        }

        /* ── Eyebrow labels ── */
        .eyebrow {
            display: inline-flex; align-items: center; gap: 0.5rem;
            font-size: 0.68rem; font-weight: 700;
            letter-spacing: 0.14em; text-transform: uppercase;
            color: var(--eb-color, #C4714A);
            margin-bottom: 0.875rem;
        }
        .eyebrow::before {
            content: ''; display: block;
            width: 1.75rem; height: 2px;
            background: currentColor; border-radius: 2px; flex-shrink: 0;
        }

        /* ── Testo gradiente ── */
        .text-grad {
            background: linear-gradient(120deg, #C4714A, #8B4513);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* ── Scroll offset per header fisso ── */
        html { scroll-padding-top: 80px; }
    </style>
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
            <a href="#funzionalita" class="text-sm font-medium transition"
               :class="scrolled ? 'text-ink-muted hover:text-ink' : 'text-white/80 hover:text-white'">Funzionalità</a>
            <a href="#prezzi" class="text-sm font-medium transition"
               :class="scrolled ? 'text-ink-muted hover:text-ink' : 'text-white/80 hover:text-white'">Prezzi</a>
            <a href="{{ route('contact') }}"
               class="shimmer text-sm font-semibold bg-terra text-white px-4 py-2 rounded-lg hover:bg-terra/90 transition shadow-sm">
                Inizia Gratis
            </a>
        </nav>

        <button @click="open = !open" class="md:hidden p-2 rounded-md transition"
                :class="scrolled ? 'text-ink' : 'text-white'">
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
            <a href="#funzionalita" @click="open = false"
               class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Funzionalità</a>
            <a href="#prezzi" @click="open = false"
               class="text-sm font-medium text-ink px-2 py-3 rounded-lg hover:bg-cream-dark transition">Prezzi</a>
            <a href="{{ route('contact') }}"
               class="mt-2 text-sm font-semibold bg-terra text-white px-4 py-3 rounded-xl text-center hover:bg-terra/90 transition">
                Inizia Gratis
            </a>
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

        <h1 class="h-title font-display text-6xl sm:text-7xl lg:text-8xl font-semibold text-white leading-[1.05] mb-6">
            Porta il tuo salone a un livello più
            <span class="text-grad"> professionale</span>
        </h1>

        <p class="h-sub text-lg sm:text-xl text-slate-300 max-w-2xl mx-auto mb-10 leading-relaxed">
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
            <a href="#come-funziona"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-300 hover:text-white transition">
                Scopri come funziona
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </a>
        </div>

        <p class="h-fine mt-6 text-xs text-slate-400">
            Nessuna carta di credito richiesta &middot; 14 giorni gratuiti &middot; Cancelli quando vuoi
        </p>
    </div>

</section>


{{-- TRUST BAR --}}
<section class="bg-cream-dark border-b border-warm-border py-10 px-6">
    <div class="max-w-4xl mx-auto">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6 text-center">
            <div class="flex flex-col items-center gap-1.5" data-r style="--d:0">
                <span class="text-2xl font-bold text-terra" data-counter="500" data-suffix="+">500+</span>
                <span class="text-xs text-ink-muted font-medium">Saloni attivi</span>
            </div>
            <div class="flex flex-col items-center gap-1.5" data-r style="--d:1">
                <span class="text-2xl font-bold text-terra" data-counter="100" data-suffix="k+">100k+</span>
                <span class="text-xs text-ink-muted font-medium">Clienti gestiti al mese</span>
            </div>
            <div class="flex flex-col items-center gap-1.5" data-r style="--d:2">
                <span class="text-2xl font-bold text-terra">7/7</span>
                <span class="text-xs text-ink-muted font-medium">Giorni di supporto</span>
            </div>
            <div class="flex flex-col items-center gap-1.5" data-r style="--d:3">
                <span class="text-2xl font-bold text-terra">GDPR</span>
                <span class="text-xs text-ink-muted font-medium">Compliant &amp; Pagamenti sicuri</span>
            </div>
        </div>
    </div>
</section>


{{-- PROBLEM --}}
<section class="py-24 px-6 bg-cream">
    <div class="max-w-5xl mx-auto">
        <div class="text-center mb-16">
            <p class="eyebrow" data-r style="--d:0">Il problema</p>
            <h2 class="font-display text-4xl sm:text-5xl font-semibold text-ink mb-4" data-r style="--d:1">
                Ancora usi carta, WhatsApp<br class="hidden sm:block"> e fogli Excel per gestire il salone?
            </h2>
            <p class="text-ink-muted max-w-xl mx-auto" data-r style="--d:2">
                Ogni giorno perdi ore preziose su problemi che si risolvono in automatico.
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['path' => 'M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
                 'title' => 'Clienti che si dimenticano',
                 'desc'  => 'Perdi tempo a richiamare e inviare messaggi a mano. I no-show ti costano soldi ogni settimana.'],
                ['path' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M3 10h18M7 15h1m4 0h1M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                 'title' => 'Incassi difficili da tracciare',
                 'desc'  => 'Contanti, carte, bonifici: a fine mese non sai mai quanto hai incassato davvero e dove.'],
                ['path' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                 'title' => 'Staff e turni da coordinare',
                 'desc'  => 'Ogni operatore con orari diversi. Senza un sistema unico, le sovrapposizioni sono inevitabili.'],
            ] as $i => $p)
            <div class="bg-cream-dark rounded-2xl p-8 border border-warm-border" data-r style="--d:{{ $i }}">
                <div class="w-11 h-11 bg-red-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-5 h-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $p['path'] }}"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-ink mb-2">{{ $p['title'] }}</h3>
                <p class="text-sm text-ink-muted leading-relaxed">{{ $p['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- FEATURES --}}
<section id="funzionalita" class="py-24 px-6 bg-slate-50 scroll-mt-16">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <p class="eyebrow" data-r style="--d:0">Funzionalità</p>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">
                Tutto quello che serve per gestire il tuo salone
            </h2>
            <p class="text-gray-500 max-w-xl mx-auto" data-r style="--d:2">
                Una piattaforma completa, progettata per farti risparmiare tempo ogni giorno.
            </p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
            @php $features = [
                ['path' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                 'title' => 'Prenotazioni Online 24/7',
                 'desc'  => 'I clienti prenotano dal telefono in qualsiasi momento. Nessuna telefonata, nessun messaggio da gestire.'],
                ['path' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                 'title' => 'Gestione Staff e Turni',
                 'desc'  => 'Ogni operatore con il proprio calendario, servizi assegnati e disponibilità personalizzata.'],
                ['path' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
                 'title' => 'Pagamenti Integrati',
                 'desc'  => 'Stripe, POS o contanti: incassa online o in salone. Ogni transazione tracciata in automatico.'],
                ['path' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9',
                 'title' => 'Promemoria Automatici',
                 'desc'  => 'Email, SMS e WhatsApp prima dell\'appuntamento. I no-show si riducono drasticamente.'],
                ['path' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                 'title' => 'Lista d\'Attesa Intelligente',
                 'desc'  => 'Slot liberati? Il sistema avvisa i clienti in attesa e gestisce le sostituzioni in autonomia.'],
                ['path' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
                 'title' => 'Report e Statistiche',
                 'desc'  => 'Incassi, servizi più richiesti e performance dello staff in tempo reale, sempre aggiornati.'],
            ]; @endphp
            @foreach ($features as $i => $f)
            <div class="f-card bg-white rounded-2xl p-7 border border-gray-100 shadow-sm transition-all duration-200 hover:shadow-md hover:-translate-y-0.5"
                 data-r style="--d:{{ $i }}">
                <div class="w-11 h-11 bg-teal-50 rounded-xl flex items-center justify-center mb-5">
                    <svg class="w-5 h-5 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $f['path'] }}"/>
                    </svg>
                </div>
                <h3 class="font-semibold text-gray-900 mb-2">{{ $f['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- HOW IT WORKS --}}
<section id="come-funziona" class="py-24 px-6 bg-white scroll-mt-16">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-16">
            <p class="eyebrow" data-r style="--d:0">Come funziona</p>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">
                Attivo in 5 minuti. Nessuna installazione.
            </h2>
            <p class="text-gray-500" data-r style="--d:2">Bastano tre passi per portare il tuo salone online.</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10 md:gap-6 relative">
            {{-- Connettore tratteggiato desktop --}}
            <div class="hidden md:block absolute top-7 left-[calc(16.7%+1.5rem)] right-[calc(16.7%+1.5rem)] h-px"
                 style="background:repeating-linear-gradient(to right,#0d9488 0,#0d9488 6px,transparent 6px,transparent 14px)"></div>

            @foreach([
                ['n'=>'01','title'=>'Registra il tuo salone',      'desc'=>'Crea il profilo, aggiungi i servizi e configura gli orari in meno di 5 minuti.'],
                ['n'=>'02','title'=>'Condividi il link',            'desc'=>'I clienti prenotano dalla tua pagina dedicata, direttamente dallo smartphone, h24.'],
                ['n'=>'03','title'=>'Gestisci tutto dal pannello',  'desc'=>'Appuntamenti, pagamenti, staff e statistiche in un\'unica schermata sempre aggiornata.'],
            ] as $i => $s)
            <div class="flex flex-col items-center text-center relative" data-r style="--d:{{ $i + 2 }}">
                <div class="w-14 h-14 rounded-full bg-teal-600 text-white flex items-center justify-center text-lg font-bold mb-5 shadow-lg shadow-teal-100 relative z-10">
                    {{ $s['n'] }}
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $s['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed max-w-xs">{{ $s['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- PRICING --}}
<section id="prezzi" class="py-24 px-6 bg-slate-50 scroll-mt-16">
    <div class="max-w-4xl mx-auto text-center">
        <p class="eyebrow justify-center" data-r style="--d:0">Prezzi</p>
        <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Prezzo semplice e trasparente</h2>
        <p class="text-gray-500 mb-2" data-r style="--d:2">Nessun costo di setup. Cancelli quando vuoi. I primi 14 giorni sono gratis.</p>
        <p class="text-xs text-gray-400 mb-12" data-r style="--d:3">Prezzi IVA esclusa</p>

        <div class="max-w-sm mx-auto" data-r style="--d:2">
            <div class="float-card bg-white rounded-2xl p-8 border border-gray-200 shadow-xl shadow-slate-200/70 flex flex-col">
                <div class="mb-6 text-left">
                    <h3 class="font-bold text-gray-900 text-lg mb-1">Piano completo</h3>
                    <div class="flex items-end gap-1 mt-2">
                        <span class="text-5xl font-bold text-gray-900">€29</span>
                        <span class="text-gray-400 text-sm mb-2">/mese</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">Per saloni di ogni dimensione</p>
                </div>
                <ul class="space-y-3 mb-8 flex-1 text-left">
                    @foreach(['Operatori illimitati','Prenotazioni online illimitate','Promemoria email automatici','Pagamenti online e in salone','Lista d\'attesa intelligente','Report e statistiche','Supporto 7 giorni su 7'] as $feat)
                    <li class="flex items-center gap-2.5 text-sm text-gray-600">
                        <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                        {{ $feat }}
                    </li>
                    @endforeach
                </ul>
                <a href="{{ route('contact') }}"
                   class="shimmer block w-full text-center text-sm font-semibold bg-teal-600 text-white py-3.5 rounded-xl hover:bg-teal-700 transition">
                    Inizia i 14 Giorni Gratis
                </a>
            </div>
        </div>
    </div>
</section>


{{-- TESTIMONIALS --}}
<section class="py-24 px-6 bg-white">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <p class="eyebrow" data-r style="--d:0">Recensioni</p>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Cosa dicono i nostri clienti</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach([
                ['quote'=>'Prima passavo mezz\'ora ogni mattina a confermare appuntamenti su WhatsApp. Adesso arrivano i clienti e basta. I promemoria pensano a tutto, io non devo fare niente.','name'=>'Giulia Rossi',  'role'=>'Parrucchiera, Milano','initial'=>'G','color'=>'bg-teal-500'],
                ['quote'=>'Ho tre colleghi in salone e prima era il caos: turni sbagliati, pagamenti da registrare a mano. Adesso tutto è in ordine e so sempre com\'è andata la settimana.',    'name'=>'Marco Torrisi', 'role'=>'Barbiere, Roma',       'initial'=>'M','color'=>'bg-indigo-500'],
                ['quote'=>'Le mie clienti prenotano quando vogliono, anche a mezzanotte. Non rispondo più a nessun messaggio per gli appuntamenti. E le prenotazioni sono aumentate.',              'name'=>'Alessia Marino','role'=>'Estetista, Torino',    'initial'=>'A','color'=>'bg-rose-500'],
            ] as $i => $t)
            <article class="bg-slate-50 rounded-2xl p-8 flex flex-col" data-r style="--d:{{ $i }}">
                <div class="flex gap-0.5 mb-5">
                    @for($s = 0; $s < 5; $s++)
                    <svg class="w-4 h-4 text-amber-400" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                    </svg>
                    @endfor
                </div>
                <p class="text-sm text-gray-600 leading-relaxed mb-6 flex-1">"{{ $t['quote'] }}"</p>
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-full {{ $t['color'] }} flex items-center justify-center text-white text-sm font-bold shrink-0">
                        {{ $t['initial'] }}
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $t['name'] }}</p>
                        <p class="text-xs text-gray-400">{{ $t['role'] }}</p>
                    </div>
                </div>
            </article>
            @endforeach
        </div>
    </div>
</section>


{{-- FAQ --}}
<section class="py-24 px-6 bg-slate-50">
    <div class="max-w-2xl mx-auto" x-data="{ active: null }">
        <div class="text-center mb-16">
            <p class="eyebrow" data-r style="--d:0">FAQ</p>
            <h2 class="text-3xl sm:text-4xl font-bold text-gray-900 mb-4" data-r style="--d:1">Domande frequenti</h2>
        </div>
        <div class="space-y-2">
            @foreach([
                ['q'=>'Quanto costa davvero?',                    'a'=>'Il piano è €29/mese, senza costi nascosti o commissioni sulle prenotazioni. I primi 14 giorni sono completamente gratuiti, nessuna carta di credito richiesta.'],
                ['q'=>'Devo installare qualcosa?',                'a'=>'No. È tutto basato sul web. Accedi da qualsiasi browser su computer, tablet o smartphone. Nessuna installazione, nessun aggiornamento manuale.'],
                ['q'=>'Posso importare i miei clienti esistenti?','a'=>'Sì. Puoi importare clienti e storico prenotazioni tramite file CSV o aggiungerli manualmente. Il nostro team ti supporta durante la migrazione.'],
                ['q'=>"C'è supporto in italiano?",                'a'=>'Sì. Il supporto è completamente in italiano, disponibile via email e chat 7 giorni su 7. Il tempo medio di risposta è sotto le 2 ore.'],
                ['q'=>'Posso cancellare quando voglio?',          'a'=>'Assolutamente. Nessun vincolo contrattuale, nessuna penale. Cancelli con un click dalla dashboard e non ti viene addebitato nulla dal mese successivo.'],
            ] as $idx => $faq)
            <div class="bg-white rounded-xl border border-gray-200 overflow-hidden" data-r style="--d:{{ $idx }}">
                <button @click="active === {{ $idx }} ? active = null : active = {{ $idx }}"
                        class="w-full flex items-center justify-between px-6 py-5 text-left font-medium text-gray-900 hover:bg-slate-50 transition-colors text-sm">
                    <span>{{ $faq['q'] }}</span>
                    <svg class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200"
                         :class="active === {{ $idx }} ? 'rotate-180' : ''"
                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="active === {{ $idx }}"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 translate-y-0"
                     x-transition:leave-end="opacity-0 -translate-y-1"
                     class="px-6 pb-5 text-sm text-gray-500 leading-relaxed border-t border-gray-100 pt-4">
                    {{ $faq['a'] }}
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>


{{-- CTA FINALE --}}
<section class="cta-gradient relative overflow-hidden py-24 px-6 text-center">
    <div class="dot-grid absolute inset-0 pointer-events-none"></div>
    <div class="blob absolute -top-32 -right-32 w-96 h-96 rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(13,148,136,0.15) 0%,transparent 70%)"></div>
    <div class="relative z-10 max-w-2xl mx-auto">
        <h2 class="text-3xl sm:text-4xl font-bold text-white mb-4" data-r style="--d:0">
            Il tuo salone merita di più. Inizia oggi.
        </h2>
        <p class="text-slate-300 mb-8 text-lg leading-relaxed" data-r style="--d:1">
            Unisciti a 500+ saloni che hanno già scelto GestionalePro.
        </p>
        <div data-r style="--d:2">
            <a href="{{ route('contact') }}"
               class="shimmer inline-flex items-center gap-2 bg-teal-500 hover:bg-teal-400 text-white font-semibold px-8 py-4 rounded-xl transition text-base shadow-lg shadow-teal-900/30">
                Inizia i 14 Giorni Gratis
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                </svg>
            </a>
            <p class="mt-4 text-xs text-slate-500">Nessuna carta di credito &middot; Cancelli quando vuoi</p>
        </div>
    </div>
</section>


{{-- FOOTER --}}
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
                    <li><a href="#funzionalita" class="hover:text-white transition">Funzionalità</a></li>
                    <li><a href="#prezzi" class="hover:text-white transition">Prezzi</a></li>
                    <li><a href="#come-funziona" class="hover:text-white transition">Come funziona</a></li>
                </ul>
            </div>
            <div>
                <h4 class="text-xs font-semibold text-slate-300 uppercase tracking-widest mb-4">Azienda</h4>
                <ul class="space-y-3 text-sm">
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Contatti</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-white transition">Supporto</a></li>
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

<script>
(function () {
    // Barra progresso scroll
    var bar = document.getElementById('spb');
    window.addEventListener('scroll', function () {
        bar.style.width = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight) * 100) + '%';
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
