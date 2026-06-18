@extends('emails.layouts.base')

@section('title')Promemoria appuntamento @endsection

@section('body')
    @php $oreRimanenti = (int) now()->diffInHours($appointment->scheduled_date, false); @endphp
    <p>Ciao {{ explode(' ', trim($appointment->user->name))[0] }}, hai un appuntamento tra {{ $oreRimanenti }} {{ $oreRimanenti === 1 ? 'ora' : 'ore' }}.</p>

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

    @if($appointment->canBeCancelled())
        @php $deadlineHours = \App\Models\SystemSetting::getCancellationDeadlineHours(); @endphp
        <p style="margin-top:20px; font-size:0.875rem; color:#6b7280;">Puoi disdire fino a {{ $deadlineHours }} {{ $deadlineHours === 1 ? 'ora' : 'ore' }} prima dell'appuntamento tramite il link qui sotto. Dopo tale termine non sarà più possibile annullare.</p>
    @endif
@endsection

@section('actions')
    <a href="{{ $confirmUrl }}" class="btn" style="background-color:#1e293b;color:#ffffff;">✓ Conferma presenza</a>
    @if($appointment->canBeCancelled())
        <a href="{{ $cancelUrl }}" class="btn btn-secondary">Disdici</a>
    @endif
@endsection
