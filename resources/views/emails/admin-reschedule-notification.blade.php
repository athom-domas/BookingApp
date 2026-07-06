@extends('emails.layouts.base')

@section('title')Appuntamento spostato dal cliente @endsection
@section('badge')Admin @endsection
@section('skip-greeting') @endsection

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
            <span class="detail-label">Nuovo orario</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <a href="{{ url('/admin/appointments/' . $appointment->id . '/edit') }}" class="btn" style="background-color: #2563eb; color: #ffffff;">Apri prenotazione</a>
@endsection

@section('footer-note')
    Accedi all'area amministrativa per gestire la prenotazione.
@endsection
