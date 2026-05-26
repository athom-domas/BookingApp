@extends('layouts.app')

@section('title', 'Lista d\'attesa')

@section('content')
    <section class="space-y-8">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
            <div>
                <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Lista d'attesa</h1>
                <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">Verrai notificato quando si libera uno slot compatibile.</p>
            </div>
            <a href="{{ route('portal.waitlist.create') }}" class="btn-primary inline-block rounded-md px-5 py-2.5 text-sm font-semibold text-center text-white">Nuova iscrizione</a>
        </div>

        @if(session('status'))
            <div class="rounded-md bg-green-50 p-4 text-sm text-green-700 dark:bg-green-900/20 dark:text-green-400">{{ session('status') }}</div>
        @endif

        @if($entries->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Non sei iscritto a nessuna lista d'attesa.</p>
        @else
            <div class="space-y-4">
                @foreach($entries as $entry)
                    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <p class="font-medium text-gray-900 dark:text-gray-100">
                                    {{ \App\Models\Service::whereIn('id', $entry->service_ids)->pluck('name')->implode(', ') }}
                                </p>
                                @if($entry->preferred_staff_id)
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Operatore: {{ $entry->preferredStaff->name }}</p>
                                @endif
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    Dal {{ $entry->preferred_date_from->format('d/m/Y') }}
                                    al {{ $entry->preferred_date_to->format('d/m/Y') }},
                                    {{ substr($entry->preferred_time_from, 0, 5) }}–{{ substr($entry->preferred_time_to, 0, 5) }}
                                </p>
                            </div>
                            @if($entry->status === 'waiting')
                                <form method="POST" action="{{ route('portal.waitlist.destroy', $entry) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm text-red-600 hover:underline dark:text-red-400">Rimuovi</button>
                                </form>
                            @else
                                <span class="text-sm font-medium text-blue-600 dark:text-blue-400">Notificato</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
@endsection
