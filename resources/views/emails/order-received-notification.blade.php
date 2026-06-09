<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <title>Nuovo ordine #{{ $order->id }}</title>
</head>
<body style="font-family: sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #1d4ed8;">Nuovo ordine prodotti #{{ $order->id }}</h2>
    <p>Ciao {{ $recipient->name }},</p>
    <p>
        <strong>{{ $order->user->name }}</strong> ha effettuato un nuovo ordine.
        Metodo di pagamento: <strong>{{ $order->payment_method === 'stripe' ? 'Online (Stripe)' : 'In salone (contanti)' }}</strong>.
    </p>
    <table style="border-collapse: collapse; width: 100%; margin: 20px 0;">
        <thead>
            <tr style="background: #f9fafb;">
                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: left;">Prodotto</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">Qtà</th>
                <th style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">Subtotale</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $item)
            <tr>
                <td style="padding: 8px; border: 1px solid #e5e7eb;">{{ $item->product?->name ?? 'Prodotto' }}</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: center;">{{ $item->quantity }}</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right;">{{ number_format($item->subtotal, 2, ',', '.') }} €</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="2" style="padding: 8px; border: 1px solid #e5e7eb; text-align: right; font-weight: bold;">Totale</td>
                <td style="padding: 8px; border: 1px solid #e5e7eb; text-align: right; font-weight: bold;">{{ number_format($order->total, 2, ',', '.') }} €</td>
            </tr>
        </tfoot>
    </table>
    @if ($order->notes)
        <p><strong>Note:</strong> {{ $order->notes }}</p>
    @endif
</body>
</html>
