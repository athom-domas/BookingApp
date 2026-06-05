@extends('emails.layouts.base')

@section('title')✓ Appuntamento confermato @endsection

@section('body')
    <p>il tuo appuntamento è stato confermato. Ti aspettiamo!</p>

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
        <div class="detail-row">
            <span class="detail-label">Prezzo</span>
            <span class="detail-value">€{{ number_format($appointment->final_price, 2, ',', '.') }}</span>
        </div>
    </div>
@endsection

@section('actions')
    <a href="{{ url('/portal/appointments') }}" class="btn" style="background-color:#1e293b;color:#ffffff;">I miei appuntamenti</a>
@endsection

@if(\App\Models\SystemSetting::getReminderCount() > 0)
@section('footer-note')
    Riceverai un promemoria prima del tuo appuntamento.
@endsection
@endif
