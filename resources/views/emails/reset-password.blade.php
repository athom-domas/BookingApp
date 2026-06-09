@extends('emails.layouts.base')

@section('title')Recupero password @endsection

@section('body')
    <p>Abbiamo ricevuto una richiesta di recupero password per il tuo account.</p>
    <p>Clicca sul pulsante qui sotto per impostare una nuova password. Il link è valido per 60 minuti.</p>
    <p style="font-size:0.875rem; color:#6b7280;">Se non hai richiesto il recupero password, ignora questa email — il tuo account è al sicuro.</p>
@endsection

@section('actions')
    <a href="{{ $url }}" class="btn" style="background-color:#1e293b;color:#ffffff;">Reimposta password</a>
@endsection
