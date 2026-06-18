@extends('emails.layouts.base')

@section('title')Vuoi prenotare un nuovo appuntamento?@endsection

@section('body')
    <p>Ciao {{ explode(' ', trim($reminder->user->name))[0] }}, è passato un po' dal tuo ultimo appuntamento.</p>
    <p>Se vuoi programmare una nuova visita, puoi prenotare direttamente dal portale.</p>
@endsection

@section('actions')
    <a href="{{ $bookingUrl }}" class="btn" style="background-color:#1e293b;color:#ffffff;">Prenota ora</a>
@endsection

@section('footer-note')
    Non vuoi più ricevere questi promemoria? <a href="{{ $unsubscribeUrl }}" style="color:#6b7280;">Disattivali qui</a>.
@endsection
