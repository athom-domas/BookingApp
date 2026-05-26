@extends('layouts.app')

@section('title', 'Prenotazione confermata')

@section('content')
    <section class="mx-auto max-w-lg space-y-6 text-center">
        <div class="rounded-xl border border-green-200 bg-green-50 p-8 dark:border-green-700 dark:bg-green-900/20">
            <h1 class="font-display text-2xl font-semibold text-green-800 dark:text-green-300">Prenotazione confermata!</h1>
            <p class="mt-2 text-sm text-green-700 dark:text-green-400">
                Il tuo appuntamento è stato prenotato con successo.
                <a href="{{ route('login') }}" class="font-medium underline">Accedi al portale</a> per vedere i dettagli e completare il pagamento.
            </p>
        </div>
    </section>
@endsection
