@extends('emails.layouts.base')

@section('title')Nuova prenotazione ricevuta @endsection
@section('badge')Admin @endsection

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
            <span class="detail-label">Data</span>
            <span class="detail-value">{{ $appointment->scheduled_date->format('d/m/Y') }} alle {{ $appointment->scheduled_date->format('H:i') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Prezzo</span>
            <span class="detail-value">
                @if($appointment->payment?->loyalty_discount_percentage)
                    <span style="text-decoration:line-through;color:#9ca3af;margin-right:4px">€{{ number_format((float) $appointment->payment->loyalty_original_amount, 2, ',', '.') }}</span>€{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }} <span style="font-size:0.75rem;color:#16a34a">(sconto fedeltà {{ $appointment->payment->loyalty_discount_percentage }}%)</span>
                @elseif($appointment->payment?->status === 'completed')
                    €{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }}
                @else
                    €{{ number_format((float) $appointment->final_price, 2, ',', '.') }}
                @endif
            </span>
        </div>
        @if($appointment->notes)
        <div class="detail-row">
            <span class="detail-label">Note</span>
            <span class="detail-value">{{ $appointment->notes }}</span>
        </div>
        @endif
    </div>
@endsection

@section('actions')
    <a href="{{ url('/admin/appointments/' . $appointment->id . '/edit') }}" class="btn" style="background-color: #2563eb; color: #ffffff;">Apri prenotazione</a>
@endsection

@section('footer-note')
    Accedi all'area amministrativa per gestire la prenotazione.
@endsection
