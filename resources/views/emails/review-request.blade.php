@extends('emails.layouts.base')

@section('title')Come è andata?@endsection

@section('body')
    <p>Ciao {{ explode(' ', trim($appointment->user->name))[0] }}, speriamo che la tua visita sia andata alla grande!</p>

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
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }}</span>
        </div>
    </div>

    <p style="margin-top:20px;">La tua opinione è preziosa. Ci vogliono solo 30 secondi per lasciare una recensione.</p>
@endsection

@section('actions')
    <a href="{{ $appointmentsUrl }}" class="btn" style="background-color:#1e293b;color:#ffffff;">⭐ Lascia una recensione</a>
@endsection
