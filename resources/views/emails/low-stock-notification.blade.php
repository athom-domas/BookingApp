<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Scorte basse: {{ $product->name }}</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #d97706;">&#9888;&#65039; Scorte basse: {{ $product->name }}</h2>
    <p>Ciao {{ $recipient->name }},</p>
    <p>Il prodotto <strong>{{ $product->name }}</strong> ha raggiunto la soglia minima di scorte.</p>
    <table style="border-collapse: collapse; width: 100%; margin: 20px 0;">
        <tr>
            <td style="padding: 8px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Scorte attuali</td>
            <td style="padding: 8px; border: 1px solid #e5e7eb; color: #dc2626; font-weight: bold;">{{ $product->stock }} pezzi</td>
        </tr>
        <tr>
            <td style="padding: 8px; border: 1px solid #e5e7eb; background: #f9fafb; font-weight: bold;">Soglia impostata</td>
            <td style="padding: 8px; border: 1px solid #e5e7eb;">{{ $product->low_stock_threshold }} pezzi</td>
        </tr>
    </table>
    <p>Accedi al pannello di amministrazione per aggiornare le scorte.</p>
</body>
</html>
