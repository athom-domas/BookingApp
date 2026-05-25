@extends('emails.layouts.base')

@section('title')Promemoria appuntamento @endsection

@section('body')
    <p>Ciao <strong>{{ $appointment->user->name }}</strong>,</p>
    <p>ti ricordiamo che hai un appuntamento <strong>{{ $hoursLabel }}</strong> alle <strong>{{ $appointment->scheduled_date->format('H:i') }}</strong>.</p>

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
    @php
        $btnColor = '#' . ltrim(preg_replace('/[^#0-9a-fA-F]/', '', \App\Models\SalonProfile::current()->primary_color ?? '#2563eb'), '#');
    @endphp
    <a href="{{ $confirmUrl }}" class="btn" style="background-color: {{ $btnColor }}; color: #ffffff;">✓ Conferma presenza</a>
    <a href="{{ $cancelUrl }}" class="btn btn-secondary">Disdici</a>
@endsection
