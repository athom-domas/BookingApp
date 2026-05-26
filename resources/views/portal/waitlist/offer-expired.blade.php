@extends('layouts.app')

@section('title', 'Offerta scaduta')

@section('content')
    <section class="mx-auto max-w-lg space-y-6 text-center">
        <div class="rounded-xl border border-red-200 bg-red-50 p-8 dark:border-red-700 dark:bg-red-900/20">
            <h1 class="font-display text-2xl font-semibold text-red-800 dark:text-red-300">Spiacente, il posto non è più disponibile</h1>
            <p class="mt-2 text-sm text-red-700 dark:text-red-400">
                Il link è scaduto o lo slot è già stato occupato da un altro cliente.
                <a href="{{ route('booking.create') }}" class="font-medium underline">Cerca altri slot disponibili</a>
                oppure <a href="{{ route('login') }}" class="font-medium underline">accedi al portale</a> per iscriverti nuovamente alla lista d'attesa.
            </p>
        </div>
    </section>
@endsection
