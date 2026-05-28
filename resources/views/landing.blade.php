<!DOCTYPE html>
<html lang="it" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestionale Prenotazioni — Software per saloni e centri estetici</title>
    <meta name="description" content="Il software di gestione prenotazioni per barbieri, parrucchieri e centri estetici. Pannello admin, portale clienti, pagamenti online e molto altro.">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .gradient-hero { background: linear-gradient(135deg, #1d1d1d 0%, #3d3d3d 100%); }
        .feature-card:hover { transform: translateY(-2px); }
    </style>
</head>
<body class="bg-white text-gray-900 antialiased">

{{-- NAV --}}
<nav class="fixed top-0 inset-x-0 z-50 bg-white/90 backdrop-blur border-b border-gray-100">
    <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <span class="font-bold text-lg tracking-tight">Gestionale<span class="text-gray-400 font-normal">Pro</span></span>
        <a href="{{ url('/superadmin/login') }}"
           class="text-sm text-gray-500 hover:text-gray-900 transition">
            Accedi →
        </a>
    </div>
</nav>

{{-- HERO --}}
<section class="gradient-hero pt-32 pb-24 px-6 text-white">
    <div class="max-w-4xl mx-auto text-center">
        <p class="text-sm font-medium text-gray-400 tracking-widest uppercase mb-4">Software gestionale</p>
        <h1 class="text-5xl font-bold leading-tight mb-6">
            Prenotazioni online<br>per il tuo salone
        </h1>
        <p class="text-xl text-gray-300 max-w-2xl mx-auto mb-10">
            Tutto quello che ti serve per gestire appuntamenti, staff, pagamenti e clienti —
            in un unico pannello semplice e professionale.
        </p>
        <a href="mailto:info@example.com"
           class="inline-block bg-white text-gray-900 font-semibold px-8 py-4 rounded-xl hover:bg-gray-100 transition text-base">
            Richiedi una demo
        </a>
    </div>
</section>

{{-- FEATURES --}}
<section class="py-24 px-6 bg-gray-50">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-bold mb-3">Tutto incluso, pronto all'uso</h2>
            <p class="text-gray-500 max-w-xl mx-auto">Nessuna configurazione complessa. Attivi il salone e in pochi minuti i tuoi clienti possono prenotare.</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

            @php
            $features = [
                ['icon' => '📅', 'title' => 'Prenotazioni online', 'desc' => 'I clienti prenotano da smartphone o desktop in qualsiasi momento, senza telefonate.'],
                ['icon' => '👥', 'title' => 'Gestione staff', 'desc' => 'Ogni operatore con il proprio calendario, servizi e disponibilità personalizzata.'],
                ['icon' => '💳', 'title' => 'Pagamenti online', 'desc' => 'Integrazione Stripe per pagare alla prenotazione, on-site o in modalità mista.'],
                ['icon' => '🔔', 'title' => 'Notifiche automatiche', 'desc' => 'Promemoria via email, SMS e WhatsApp. I no-show si riducono drasticamente.'],
                ['icon' => '📋', 'title' => 'Lista d\'attesa', 'desc' => 'Slot liberati? Il sistema avvisa automaticamente i clienti in lista e gestisce le offerte.'],
                ['icon' => '📊', 'title' => 'Dashboard e report', 'desc' => 'Panoramica degli appuntamenti, incassi e performance dello staff in tempo reale.'],
                ['icon' => '🏪', 'title' => 'Vetrina pubblica', 'desc' => 'Ogni salone ha la propria pagina branded con foto, servizi, staff, recensioni e orari.'],
                ['icon' => '🔧', 'title' => 'Pannello admin', 'desc' => 'Gestione completa di appuntamenti, clienti, servizi e impostazioni da un\'unica interfaccia.'],
                ['icon' => '🌐', 'title' => 'Multi-salone', 'desc' => 'Gestisci più sedi o clienti da un unico account superadmin con dati completamente separati.'],
            ];
            @endphp

            @foreach ($features as $f)
            <div class="feature-card bg-white rounded-2xl p-6 border border-gray-100 shadow-sm transition duration-200">
                <div class="text-3xl mb-3">{{ $f['icon'] }}</div>
                <h3 class="font-semibold text-gray-900 mb-1">{{ $f['title'] }}</h3>
                <p class="text-sm text-gray-500 leading-relaxed">{{ $f['desc'] }}</p>
            </div>
            @endforeach
        </div>
    </div>
</section>

{{-- FOR WHO --}}
<section class="py-24 px-6">
    <div class="max-w-4xl mx-auto text-center">
        <h2 class="text-3xl font-bold mb-3">Pensato per</h2>
        <p class="text-gray-500 mb-12">Qualsiasi attività che lavora su appuntamento</p>
        <div class="flex flex-wrap justify-center gap-3">
            @foreach (['Barbieri', 'Parrucchieri', 'Centri estetici', 'Nail studio', 'Tatuatori', 'Massaggiatori', 'Personal trainer', 'Fisioterapisti'] as $cat)
                <span class="px-5 py-2 bg-gray-100 rounded-full text-sm font-medium text-gray-700">{{ $cat }}</span>
            @endforeach
        </div>
    </div>
</section>

{{-- CTA --}}
<section class="gradient-hero py-20 px-6 text-white text-center">
    <div class="max-w-2xl mx-auto">
        <h2 class="text-3xl font-bold mb-4">Pronto a portare il tuo salone online?</h2>
        <p class="text-gray-300 mb-8">Contattaci per una demo gratuita e senza impegno.</p>
        <a href="mailto:info@example.com"
           class="inline-block bg-white text-gray-900 font-semibold px-8 py-4 rounded-xl hover:bg-gray-100 transition">
            Contattaci
        </a>
    </div>
</section>

<footer class="bg-gray-900 text-gray-500 text-sm py-8 px-6 text-center">
    © {{ date('Y') }} Gestionale Prenotazioni. Tutti i diritti riservati.
</footer>

</body>
</html>
