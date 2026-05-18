<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appuntamento annullato</title>
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
        }
        html.dark {
            --color-body-bg: #111827;
            --color-body-text: #e5e7eb;
            --color-card-bg: #1f2937;
            --color-muted: #9ca3af;
        }
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: var(--color-body-text); background: var(--color-body-bg); }
        .card { background: var(--color-card-bg); border-radius: 12px; padding: 32px; text-align: center; }
        h1 { font-size: 1.5rem; margin-bottom: 8px; }
        p { color: var(--color-muted); }
    </style>
</head>
<body>
    <div class="card">
        @if($alreadyDone)
            <h1>Già annullato</h1>
            <p>Questo appuntamento non può essere annullato.</p>
        @else
            <h1>Appuntamento annullato</h1>
            <p>Il tuo appuntamento è stato annullato. Puoi prenotarne uno nuovo quando vuoi.</p>
        @endif
    </div>
</body>
</html>
