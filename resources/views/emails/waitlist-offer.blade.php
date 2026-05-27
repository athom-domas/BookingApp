@extends('emails.layouts.base')

@section('title')Posto disponibile!@endsection

@section('body')
    <p>Ciao <strong>{{ $entry->user->name }}</strong>,</p>
    <p>si è liberato uno slot compatibile con la tua lista d'attesa.</p>
    <p><strong>Nota:</strong> questa notifica è stata inviata a tutti gli iscritti compatibili, sarà prenotato il primo che risponde.</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Servizi</span>
            <span class="detail-value">{{ \App\Models\Service::whereIn('id', $entry->service_ids)->pluck('name')->implode(', ') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ \Carbon\Carbon::parse($entry->offered_slot['date'])->locale('it')->isoFormat('dddd D MMMM YYYY') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Ora</span>
            <span class="detail-value">{{ $entry->offered_slot['time'] }}</span>
        </div>
    </div>
@endsection

@section('actions')
    @php
        $btnColor = '#' . ltrim(preg_replace('/[^#0-9a-fA-F]/', '', \App\Models\SalonProfile::current()->primary_color ?? '#1d1d1d'), '#');
    @endphp
    <a href="{{ $offerUrl }}" class="btn" style="background-color: {{ $btnColor }}; color: #ffffff;">Prenota ora</a>
@endsection

@section('footer-note')
    Il primo a cliccare ottiene la prenotazione. Il link è valido per 7 giorni.
@endsection
