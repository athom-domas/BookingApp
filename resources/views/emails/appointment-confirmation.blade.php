@extends('emails.layouts.base')

@section('title')✓ Appuntamento confermato @endsection

@section('greeting')
@php
    $salon = \App\Models\SalonProfile::current();
    $_g = $salon->email_greeting ?: 'Ciao {nome},';
    $_fn = explode(' ', trim($appointment->user->name ?? ''))[0] ?? '';
    $_g = $_fn ? str_replace('{nome}', e($_fn), $_g)
               : (str_replace('{nome}', '', trim($_g, ' ,')) ?: '');
@endphp
@if($_g)
    <p style="color:#111827;font-size:1rem;font-weight:500;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid #f3f4f6;">{!! nl2br(e($_g)) !!}</p>
@endif
@endsection

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
            <span class="detail-value">
                @if($appointment->payment?->loyalty_discount_percentage)
                    <span style="text-decoration:line-through;color:#9ca3af;margin-right:4px;">€{{ number_format((float) $appointment->payment->loyalty_original_amount, 2, ',', '.') }}</span>€{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }}<br><span style="font-size:0.75rem;color:#16a34a;font-weight:400;">sconto fedeltà {{ $appointment->payment->loyalty_discount_percentage }}%</span>
                @elseif($appointment->payment?->status === 'completed')
                    €{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }}
                @else
                    €{{ number_format((float) $appointment->final_price, 2, ',', '.') }}
                @endif
            </span>
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
