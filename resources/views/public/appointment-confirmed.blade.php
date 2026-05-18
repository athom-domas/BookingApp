<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appuntamento confermato</title>
    <script>
        if (localStorage.theme === 'dark' ||
            (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark')
        }
    </script>
    <style>
        :root {
            --color-body-bg: #fff;
            --color-body-text: #333;
            --color-card-bg: #f9fafb;
            --color-muted: #6b7280;
            --color-strong: #111;
        }
        html.dark {
            --color-body-bg: #111827;
            --color-body-text: #e5e7eb;
            --color-card-bg: #1f2937;
            --color-muted: #9ca3af;
            --color-strong: #f9fafb;
        }
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: var(--color-body-text); background: var(--color-body-bg); }
        .card { background: var(--color-card-bg); border-radius: 12px; padding: 32px; text-align: center; }
        h1 { color: #16a34a; font-size: 1.5rem; margin-bottom: 8px; }
        p { color: var(--color-muted); }
        .detail { margin: 20px 0; font-size: 1rem; }
        strong { color: var(--color-strong); }
    </style>
</head>
<body>
    <div class="card">
        @if($alreadyPast)
            <h1>Appuntamento non disponibile</h1>
            <p>Questo appuntamento è già passato o annullato.</p>
        @else
            <h1>✓ Perfetto, ci vediamo!</h1>
            <p>Abbiamo registrato la tua conferma.</p>
            <div class="detail">
                <strong>{{ $appointment->service->name }}</strong><br>
                {{ $appointment->scheduled_date->format('d/m/Y') }} alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong><br>
                con {{ $appointment->staff->name }}
            </div>
        @endif
    </div>
</body>
</html>
