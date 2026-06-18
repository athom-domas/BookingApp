@extends('emails.layouts.base')

@section('title')Promemoria appuntamento @endsection

@section('body')
    <p>Ciao {{ explode(' ', trim($appointment->user->name))[0] }}, ti ricordiamo che hai un appuntamento tra poco.</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Servizi</span>
            <span class="detail-value">{{ $appointment->services_label }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Con</span>
            <span class="detail-value">{{ $appointment->staff->name }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
    </div>

    <p style="font-size:0.875rem; color:#6b7280;">Puoi disdire fino a 24 ore prima dell'appuntamento tramite il link qui sotto. Dopo tale termine non sarà più possibile annullare.</p>
@endsection

@section('actions')
    <a href="{{ $confirmUrl }}" class="btn" style="background-color:#1e293b;color:#ffffff;">✓ Conferma presenza</a>
    <a href="{{ $cancelUrl }}" class="btn btn-secondary">Disdici</a>
@endsection
