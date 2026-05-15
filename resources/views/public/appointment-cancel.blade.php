<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disdici appuntamento</title>
    <style>
        body { font-family: sans-serif; max-width: 480px; margin: 60px auto; padding: 0 20px; color: #333; }
        .card { background: #f9fafb; border-radius: 12px; padding: 32px; }
        h1 { color: #dc2626; font-size: 1.5rem; margin-bottom: 8px; }
        .detail { margin: 16px 0; padding: 16px; background: #fff; border-radius: 8px; font-size: 0.95rem; }
        textarea { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 8px; font-size: 0.95rem; resize: vertical; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #dc2626; color: white; border: none; border-radius: 8px; font-size: 1rem; cursor: pointer; margin-top: 16px; }
        button:hover { background: #b91c1c; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #374151; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Disdici appuntamento</h1>
        <div class="detail">
            <strong>{{ $appointment->service->name }}</strong><br>
            {{ $appointment->scheduled_date->format('d/m/Y') }} alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong><br>
            con {{ $appointment->staff->name }}
        </div>
        <form method="POST" action="{{ request()->url() }}">
            @csrf
            <label for="reason">Motivo (opzionale)</label>
            <textarea id="reason" name="reason" rows="3" placeholder="Es. impegno improvviso..."></textarea>
            <button type="submit">Conferma annullamento</button>
        </form>
    </div>
</body>
</html>
