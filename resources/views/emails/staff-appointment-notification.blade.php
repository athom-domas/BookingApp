@extends('emails.layouts.base')

@section('title')Nuovo appuntamento @endsection
@section('badge')Staff @endsection
@section('skip-greeting') @endsection

@section('body')
    <p>Ciao <strong>{{ $appointment->staff->name }}</strong>,</p>
    <p>hai un nuovo appuntamento confermato.</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Cliente</span>
            <span class="detail-value">{{ $appointment->user->name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Servizi</span>
            <span class="detail-value">{{ $appointment->services_label }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
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
    Puoi gestire l'appuntamento dall'area amministrativa.
@endsection
