@extends('layouts.app')

@section('title', 'Posto già prenotato')

@section('content')
    <section class="mx-auto max-w-lg space-y-6 text-center">
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-8 dark:border-amber-700 dark:bg-amber-900/20">
            <h1 class="font-display text-2xl font-semibold text-amber-800 dark:text-amber-300">Qualcuno ha prenotato prima di te</h1>
            <p class="mt-2 text-sm text-amber-700 dark:text-amber-400">
                Il posto è stato preso da un altro iscritto pochi istanti fa. Sei ancora in lista d'attesa e riceverai una nuova notifica non appena si libera un altro slot.
            </p>
            <div class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <a href="{{ route('booking.create') }}" class="btn-primary rounded-md px-5 py-2.5 text-sm font-semibold text-white">Cerca altri slot</a>
                <a href="{{ route('login') }}" class="text-sm text-amber-700 underline hover:text-amber-900 dark:text-amber-400 dark:hover:text-amber-200">Accedi al portale</a>
            </div>
        </div>
    </section>
@endsection
