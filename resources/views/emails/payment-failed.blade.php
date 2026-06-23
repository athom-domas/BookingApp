<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family:sans-serif;max-width:600px;margin:0 auto;padding:24px">
    <h2 style="color:#dc2626">Pagamento non riuscito</h2>
    <p>Il pagamento per l'abbonamento BookingApp del salone
       <strong>{{ $business->name }}</strong> non è andato a buon fine.</p>
    <p>Per aggiornare il metodo di pagamento ed evitare l'interruzione del servizio,
       accedi al pannello e vai su <strong>Abbonamento</strong>.</p>
    <p>Per assistenza rispondi a questa email.</p>
    <p style="color:#6b7280;font-size:12px;margin-top:32px">
        BookingApp — {{ now()->format('d/m/Y') }}
    </p>
</body>
</html>
