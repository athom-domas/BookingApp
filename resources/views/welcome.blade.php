@extends('layouts.app')

@section('title', 'Benvenuto')

@section('content')
    <section class="space-y-12">
        <div class="text-center space-y-4 py-12">
            <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">Booking App</p>
            <h1 class="text-4xl font-bold text-gray-950 dark:text-gray-50 sm:text-5xl">
                Prenota il tuo appuntamento
            </h1>
            <p class="mx-auto max-w-xl text-base leading-7 text-gray-600 dark:text-gray-400">
                Scegli tra i nostri servizi, seleziona il professionista e trova l'orario che fa per te.
            </p>
            <a href="{{ route('booking.create') }}"
               class="inline-block rounded-md bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                Prenota ora
            </a>
        </div>

        <div class="space-y-4">
            <h2 class="text-xl font-semibold text-gray-950 dark:text-gray-50">I nostri servizi</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($services as $service)
                    <article class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <h3 class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ $service->name }}</h3>
                            <span class="shrink-0 rounded-md bg-blue-50 dark:bg-blue-950 px-2.5 py-1 text-sm font-semibold text-blue-700 dark:text-blue-300">
                                {{ number_format((float) $service->price, 2, ',', '.') }} €
                            </span>
                        </div>
                        @if ($service->description)
                            <p class="mt-2 text-sm leading-6 text-gray-600 dark:text-gray-400">{{ $service->description }}</p>
                        @endif
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-500">Durata: {{ $service->duration_minutes }} min</p>
                    </article>
                @empty
                    <p class="col-span-full text-sm text-gray-500 dark:text-gray-500">Nessun servizio attivo al momento.</p>
                @endforelse
            </div>
        </div>
    </section>
@endsection
