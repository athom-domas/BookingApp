@extends('emails.layouts.base')

@section('title')Scorte basse: {{ $product->name }}@endsection
@section('badge')Admin@endsection

@section('body')
    <p>Il prodotto <strong>{{ $product->name }}</strong> ha raggiunto la soglia minima di scorte.</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Scorte attuali</span>
            <span class="detail-value" style="color:#dc2626;">{{ $product->stock }} pezzi</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Soglia impostata</span>
            <span class="detail-value">{{ $product->low_stock_threshold }} pezzi</span>
        </div>
    </div>
@endsection

@section('actions')
    <a href="{{ $adminUrl }}" class="btn" style="background-color:#2563eb; color:#ffffff;">Aggiorna scorte</a>
@endsection

@section('footer-note')
    Accedi all'area amministrativa per aggiornare le scorte del prodotto.
@endsection
