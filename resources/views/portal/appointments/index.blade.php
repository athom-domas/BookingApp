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

        @if (session('review_success'))
            <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-5 py-4 text-sm text-green-800 dark:text-green-300">
                Grazie! La tua recensione è stata inviata e sarà pubblicata dopo la revisione.
            </div>
        @endif

        @if ($loyaltyEnabled)
            @include('portal.appointments.partials.loyalty-card')
        @endif

        @if($waitlistEntries->isNotEmpty())
            <div class="space-y-3">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Lista d'attesa</h2>
                <div class="space-y-3">
                    @foreach($waitlistEntries as $entry)
                        @php
                            $serviceNames = \App\Models\Service::whereIn('id', $entry->service_ids)->pluck('name')->implode(', ');
                            $dates = collect($entry->preferred_days)->sort()->map(fn ($iso) => \Carbon\Carbon::parse($iso)->isoFormat('D MMM'))->implode(', ');
                        @endphp
                        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1 min-w-0">
                                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $serviceNames }}</p>
                                    @if($entry->preferred_staff_id)
                                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $entry->preferredStaff->name }}</p>
                                    @endif
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $dates }} · {{ substr($entry->preferred_time_from, 0, 5) }}–{{ substr($entry->preferred_time_to, 0, 5) }}</p>
                                </div>
                                <div class="flex shrink-0 flex-col items-end gap-2">
                                    @if($entry->status === 'notified')
                                        <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Offerta inviata</span>
                                    @else
                                        <span class="rounded-full bg-yellow-50 px-2.5 py-0.5 text-xs font-medium text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300">In attesa</span>
                                        <form method="POST" action="{{ route('portal.waitlist.destroy', $entry) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center gap-1 rounded border border-red-200 px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:border-red-800/50 dark:text-red-400 dark:hover:bg-red-900/20 transition-colors">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
                                                Rimuovi
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

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
