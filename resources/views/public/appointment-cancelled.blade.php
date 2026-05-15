<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appuntamento annullato</title>
    <style>
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: #333; }
        .card { background: #f9fafb; border-radius: 12px; padding: 32px; text-align: center; }
        h1 { font-size: 1.5rem; margin-bottom: 8px; }
        p { color: #6b7280; }
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
