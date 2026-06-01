@extends('emails.layouts.base')

@section('title')Appuntamento disdetto @endsection

@section('body')
    <p>Ciao <strong>{{ $recipient->name }}</strong>,</p>
    <p>il seguente appuntamento è stato disdetto.</p>

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
@endsection

@section('actions')
    <a href="{{ url('/portal/appointments') }}" class="btn" style="background-color:#1e293b;color:#ffffff;">I miei appuntamenti</a>
@endsection

@section('footer-note')
    Per prenotare un nuovo appuntamento visita il nostro portale.
@endsection
