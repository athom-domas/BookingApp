@extends('layouts.app')

@section('title', 'I miei appuntamenti')

@section('content')
    <section class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">I miei appuntamenti</h1>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Prenotazioni future, pagamenti e storico.</p>
            </div>
            <a href="{{ route('booking.create') }}" class="btn-primary inline-block rounded-md px-5 py-2.5 text-sm font-semibold text-center text-white">Nuova prenotazione</a>
        </div>

        @include('portal.appointments.partials.list', [
            'title' => 'Prossimi appuntamenti',
            'appointments' => $upcomingAppointments,
            'empty' => 'Non hai appuntamenti futuri.',
        ])

        @include('portal.appointments.partials.list', [
            'title' => 'Storico',
            'appointments' => $pastAppointments,
            'empty' => 'Lo storico è vuoto.',
        ])
    </section>
@endsection
