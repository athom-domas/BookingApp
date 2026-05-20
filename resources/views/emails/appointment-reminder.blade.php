<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 520px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; }
        .header { background: #4f46e5; padding: 28px 32px; color: white; }
        .header h1 { margin: 0; font-size: 1.25rem; font-weight: 600; }
        .body { padding: 28px 32px; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f3f4f6; font-size: 0.95rem; }
        .detail-row:last-child { border-bottom: none; }
        .label { color: #6b7280; }
        .value { font-weight: 600; color: #111; }
        .actions { padding: 0 32px 32px; display: flex; gap: 12px; }
        .btn-confirm { flex: 1; padding: 13px; background: #16a34a; color: white; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95rem; display: block; }
        .btn-cancel { flex: 1; padding: 13px; background: #f3f4f6; color: #374151; text-align: center; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.95rem; display: block; }
        .footer { padding: 20px 32px; font-size: 0.8rem; color: #9ca3af; border-top: 1px solid #f3f4f6; }
    </style>
</head>
<body>
    <div class="container">
        @include('emails.partials.header')
        <div class="header">
            <h1>Promemoria appuntamento</h1>
        </div>
        <div class="body">
            <p>Ciao <strong>{{ $appointment->user->name }}</strong>,</p>
            <p>ti ricordiamo il tuo appuntamento <strong>{{ $hoursLabel }}</strong> alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong>.</p>

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
            </div>
        </div>
        <div class="actions">
            <a href="{{ $confirmUrl }}" class="btn-confirm">✓ Conferma presenza</a>
            <a href="{{ $cancelUrl }}" class="btn-cancel">Disdici</a>
        </div>
        <div class="footer">
            I link di azione scadono entro 48 ore. Per modificare l'orario accedi al portale.
        </div>
        @include('emails.partials.salon-footer')
    </div>
</body>
</html>
