@extends('emails.layouts.base')

@section('title')Nuovo ordine prodotti #{{ $order->id }}@endsection
@section('badge')Admin@endsection

@section('body')
    <div class="detail-card" style="margin-top: 0;">
        <div class="detail-row">
            <span class="detail-label">Cliente</span>
            <span class="detail-value">
                {{ $order->user->name }}<br>
                <span style="font-weight:400; color:#6b7280; font-size:0.825rem;">{{ $order->user->email }}</span>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Pagamento</span>
            <span class="detail-value">{{ $order->payment_method === 'stripe' ? 'Online (Stripe)' : 'In salone (contanti)' }}</span>
        </div>
        @foreach ($order->items as $item)
            <div class="detail-row">
                <span class="detail-label">{{ $item->product?->name ?? 'Prodotto' }}</span>
                <span class="detail-value">
                    × {{ $item->quantity }}
                    <span style="font-weight:400; color:#6b7280; font-size:0.825rem; display:block;">{{ number_format($item->subtotal, 2, ',', '.') }} €</span>
                </span>
            </div>
        @endforeach
        <div class="detail-row">
            <span class="detail-label" style="font-weight:600; color:#111827;">Totale</span>
            <span class="detail-value" style="font-size:1rem;">{{ number_format($order->total, 2, ',', '.') }} €</span>
        </div>
        @if ($order->notes)
            <div class="detail-row">
                <span class="detail-label">Note</span>
                <span class="detail-value" style="font-weight:400;">{{ $order->notes }}</span>
            </div>
        @endif
    </div>
@endsection

@section('actions')
    <a href="{{ url('/admin/product-orders/' . $order->id) }}" class="btn" style="background-color:#2563eb; color:#ffffff;">Apri ordine</a>
@endsection

@section('footer-note')
    Accedi all'area amministrativa per gestire l'ordine.
@endsection
