<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Servizio temporaneamente non disponibile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center px-6">
    <div class="max-w-md w-full text-center">

        <div class="w-16 h-16 rounded-2xl bg-slate-100 flex items-center justify-center mx-auto mb-6">
            <svg class="w-8 h-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z"/>
            </svg>
        </div>

        <h1 class="text-xl font-bold text-slate-800 mb-2">
            Prenotazioni online temporaneamente sospese
        </h1>
        <p class="text-slate-500 text-sm leading-relaxed">
            @if (!empty($business->name))
                <strong class="text-slate-700">{{ $business->name }}</strong> non accetta
                prenotazioni online in questo momento.
            @else
                Questo salone non accetta prenotazioni online in questo momento.
            @endif
            <br>
            Contatta direttamente il salone per fissare un appuntamento.
        </p>

    </div>
</body>
</html>
