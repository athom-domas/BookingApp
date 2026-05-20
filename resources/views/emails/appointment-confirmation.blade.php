<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 520px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; }
        .header { background: #16a34a; padding: 28px 32px; color: white; }
        .header h1 { margin: 0; font-size: 1.25rem; font-weight: 600; }
        .body { padding: 28px 32px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 0.95rem; }
        .detail-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111; }
        .footer { padding: 20px 32px; font-size: 0.8rem; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        @include('emails.partials.header')
        <div class="header">
            <h1>✓ Appuntamento confermato</h1>
        </div>
        <div class="body">
            <p>Ciao <strong>{{ $appointment->user->name }}</strong>,</p>
            <p>il tuo appuntamento è stato confermato. Ti aspettiamo!</p>

            <div style="margin: 20px 0; padding: 16px; background: #f9fafb; border-radius: 8px;">
                <div class="detail-row">
                    <span class="label">Servizi</span>
                    <span class="value">{{ $appointment->services_label }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Con</span>
                    <span class="value">{{ $appointment->staff->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Data</span>
                    <span class="value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Prezzo</span>
                    <span class="value">€{{ number_format($appointment->final_price, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="footer">
            Riceverai un promemoria prima dell'appuntamento.
        </div>
        @include('emails.partials.salon-footer')
    </div>
</body>
</html>
