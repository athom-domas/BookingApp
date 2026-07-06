@extends('emails.layouts.base')

@section('title')Appuntamento spostato @endsection

@section('body')
    <p>Ciao {{ explode(' ', trim($appointment->user->name))[0] }}, il tuo appuntamento è stato spostato al seguente orario.</p>

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
            <span class="detail-label">Nuova data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <a href="{{ url('/portal/appointments') }}" class="btn" style="background-color:#1e293b;color:#ffffff;">I miei appuntamenti</a>
@endsection

@section('footer-note')
    Se non hai richiesto questo spostamento, contattaci.
@endsection
