<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #1e293b; background: #f8fafc; margin: 0; padding: 40px 20px; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; border: 1px solid #e2e8f0; overflow: hidden; }
        .header { background: linear-gradient(135deg, #0f172a, #134e4a); padding: 28px 32px; }
        .header h1 { color: #fff; font-size: 18px; font-weight: 700; margin: 0; }
        .header p  { color: #94a3b8; font-size: 13px; margin: 4px 0 0; }
        .body { padding: 28px 32px; }
        .row { margin-bottom: 20px; }
        .label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: #64748b; margin-bottom: 4px; }
        .value { font-size: 15px; color: #0f172a; }
        .message-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; font-size: 14px; line-height: 1.7; white-space: pre-wrap; color: #334155; }
        .footer { padding: 16px 32px; border-top: 1px solid #f1f5f9; font-size: 12px; color: #94a3b8; }
        .badge { display: inline-block; background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; border-radius: 20px; padding: 2px 10px; font-size: 12px; font-weight: 600; }
    </style>
</head>
<body>
<div class="card">
    <div class="header">
        <h1>Nuovo messaggio dal sito</h1>
        <p>BookingApp — modulo di contatto</p>
    </div>
    <div class="body">
        <div class="row">
            <div class="label">Tipo richiesta</div>
            <div class="value">
                @php
                    $labels = ['demo' => 'Richiesta demo', 'info' => 'Informazioni generali', 'support' => 'Supporto tecnico', 'other' => 'Altra richiesta'];
                @endphp
                <span class="badge">{{ $labels[$data['subject']] ?? $data['subject'] }}</span>
            </div>
        </div>
        <div class="row">
            <div class="label">Nome</div>
            <div class="value">{{ $data['name'] }}</div>
        </div>
        <div class="row">
            <div class="label">Email</div>
            <div class="value"><a href="mailto:{{ $data['email'] }}" style="color:#0d9488">{{ $data['email'] }}</a></div>
        </div>
        @if(!empty($data['phone']))
        <div class="row">
            <div class="label">Telefono</div>
            <div class="value">{{ $data['phone'] }}</div>
        </div>
        @endif
        <div class="row">
            <div class="label">Messaggio</div>
            <div class="message-box">{{ $data['message'] }}</div>
        </div>
    </div>
    <div class="footer">
        Inviato da {{ $data['name'] }} &lt;{{ $data['email'] }}&gt; · {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
</body>
</html>
