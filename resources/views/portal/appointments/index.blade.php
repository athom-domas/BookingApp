@extends('layouts.app')

@section('title', 'I miei appuntamenti')

@section('content')
    <section class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold text-gray-950">I miei appuntamenti</h1>
                <p class="mt-2 text-sm text-gray-600">Prenotazioni future, pagamenti e storico.</p>
            </div>
            <a href="{{ route('booking.index') }}" class="rounded-md bg-blue-700 px-4 py-3 text-center text-sm font-semibold text-white shadow-sm hover:bg-blue-800">Nuova prenotazione</a>
        </div>

        @include('portal.appointments.partials.list', [
            'title' => 'Prossimi appuntamenti',
            'appointments' => $upcomingAppointments,
            'empty' => 'Non hai appuntamenti futuri.',
        ])

        @include('portal.appointments.partials.list', [
            'title' => 'Storico',
            'appointments' => $pastAppointments,
            'empty' => 'Lo storico e vuoto.',
        ])
    </section>
@endsection
