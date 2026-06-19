@extends('emails.layouts.base')

@section('title')Benvenuto al portale @endsection

@section('greeting')
@php
    $salon = \App\Models\SalonProfile::current();
    $_g = $salon->email_greeting ?: 'Ciao {nome},';
    $_fn = explode(' ', trim($recipient->name ?? ''))[0] ?? '';
    $_g = $_fn ? str_replace('{nome}', e($_fn), $_g)
               : (str_replace('{nome}', '', trim($_g, ' ,')) ?: '');
@endphp
@if($_g)
    <p style="color:#111827;font-size:1rem;font-weight:500;padding-bottom:16px;margin-bottom:16px;border-bottom:1px solid #f3f4f6;">{!! nl2br(e($_g)) !!}</p>
@endif
@endsection

@section('body')
    <p>il tuo account è stato creato. Puoi accedere al portale con le seguenti credenziali:</p>

    <div class="detail-card">
        <div class="detail-row">
            <span class="detail-label">Email</span>
            <span class="detail-value">{{ $recipient->email }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Password temporanea</span>
            <span class="detail-value" style="font-family:monospace;letter-spacing:0.05em;font-size:1rem;">{{ $tempPassword }}</span>
        </div>
    </div>

    <p style="font-size:0.85rem;color:#6b7280;margin-top:16px;">Per la tua sicurezza ti consigliamo di cambiare la password dopo il primo accesso.</p>
@endsection

@section('actions')
    <a href="{{ route('login') }}" class="btn" style="background-color:#1e293b;color:#ffffff;">Accedi al portale</a>
@endsection
