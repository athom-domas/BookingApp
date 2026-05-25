@extends('layouts.app')

@section('title', 'I miei appuntamenti')

@section('content')
    <section class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="text-3xl font-semibold text-gray-950 dark:text-gray-50">I miei appuntamenti</h1>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Prenotazioni future, pagamenti e storico.</p>
            </div>
            <a href="{{ route('booking.create') }}" class="inline-block rounded-md px-6 py-3 text-sm font-semibold text-center text-white shadow-sm" style="background-color: var(--color-primary)">Nuova prenotazione</a>
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
