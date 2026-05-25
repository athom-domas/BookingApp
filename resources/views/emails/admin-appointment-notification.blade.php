@extends('emails.layouts.base')

@section('title')Nuova prenotazione ricevuta@endsection
@section('badge')Admin@endsection

@section('body')
    <div class="detail-card" style="margin-top: 0;">
        <div class="detail-row">
            <span class="detail-label">Cliente</span>
            <span class="detail-value">{{ $appointment->user->name }}<br><span style="font-weight:400; color:#6b7280; font-size:0.825rem;">{{ $appointment->user->email }}</span></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Servizi</span>
            <span class="detail-value">{{ $appointment->services_label }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Operatore</span>
            <span class="detail-value">{{ $appointment->staff->name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Prezzo</span>
            <span class="detail-value">€{{ number_format($appointment->final_price, 2, ',', '.') }}</span>
        </div>
        @if($appointment->notes)
        <div class="detail-row">
            <span class="detail-label">Note</span>
            <span class="detail-value">{{ $appointment->notes }}</span>
        </div>
        @endif
    </div>
@endsection

@section('footer-note')
    Accedi all'area amministrativa per gestire la prenotazione.
@endsection
