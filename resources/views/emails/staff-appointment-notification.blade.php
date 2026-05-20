<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: sans-serif; background: #f3f4f6; margin: 0; padding: 20px; }
        .container { max-width: 520px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; }
        .header { background: #0ea5e9; padding: 28px 32px; color: white; }
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
            <h1>Nuovo appuntamento</h1>
        </div>
        <div class="body">
            <p>Ciao <strong>{{ $appointment->staff->name }}</strong>,</p>
            <p>hai un nuovo appuntamento confermato.</p>

            <div style="margin: 20px 0; padding: 16px; background: #f9fafb; border-radius: 8px;">
                <div class="detail-row">
                    <span class="label">Cliente</span>
                    <span class="value">{{ $appointment->user->name }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Servizi</span>
                    <span class="value">{{ $appointment->services_label }}</span>
                </div>
                <div class="detail-row">
                    <span class="label">Data</span>
                    <span class="value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
                </div>
                @if($appointment->notes)
                <div class="detail-row">
                    <span class="label">Note</span>
                    <span class="value">{{ $appointment->notes }}</span>
                </div>
                @endif
            </div>
        </div>
        <div class="footer">
            Puoi gestire l'appuntamento dall'area admin.
        </div>
        @include('emails.partials.salon-footer')
    </div>
</body>
</html>
