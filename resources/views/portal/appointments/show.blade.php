@extends('layouts.app')

@section('title', 'Dettaglio appuntamento')

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <p class="mb-1 text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Appuntamento</p>
                    <h1 class="font-display text-2xl font-semibold text-gray-950 dark:text-gray-50">{{ $appointment->services_label }}</h1>
                    <p class="mt-1 text-sm tabular-nums text-gray-500 dark:text-gray-400">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</p>
                </div>
                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
            </div>

            <dl class="mt-8 grid gap-6 sm:grid-cols-2 border-t border-gray-100 dark:border-gray-800 pt-8">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Staff</dt>
                    <dd class="mt-1.5 flex items-center gap-3">
                        @php $avatarUrl = $appointment->staff->avatarUrl(); @endphp
                        @if ($avatarUrl)
                            <img src="{{ $avatarUrl }}" alt="{{ $appointment->staff->name }}" class="w-10 h-10 rounded-full object-cover shrink-0">
                        @else
                            <span class="inline-flex w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-800 items-center justify-center text-sm font-semibold text-gray-600 dark:text-gray-300 shrink-0">{{ strtoupper(mb_substr($appointment->staff->name, 0, 1)) }}</span>
                        @endif
                        <span class="text-base text-gray-950 dark:text-gray-50">{{ $appointment->staff->name }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Durata</dt>
                    <dd class="mt-1.5 text-base tabular-nums text-gray-950 dark:text-gray-50">{{ $appointment->services->sum('duration_minutes') }} min</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Importo</dt>
                    <dd class="mt-1.5 tabular-nums">
                        @if($appointment->payment?->loyalty_discount_percentage)
                            <span class="text-sm line-through text-gray-400 dark:text-gray-500 mr-1">{{ number_format((float) $appointment->payment->loyalty_original_amount, 2, ',', '.') }} €</span>
                            <span class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }} €</span>
                            <span class="ml-1.5 inline-block rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-xs font-semibold text-green-700 dark:text-green-300">Sconto fedeltà {{ $appointment->payment->loyalty_discount_percentage }}%</span>
                        @elseif($appointment->payment?->status === 'completed')
                            <span class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ number_format((float) $appointment->payment->amount, 2, ',', '.') }} €</span>
                        @else
                            <span class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ number_format((float) $appointment->final_price, 2, ',', '.') }} €</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500">Pagamento</dt>
                    <dd class="mt-1.5">
                        @if ($appointment->payment)
                            @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                        @else
                            <span class="text-sm text-gray-400 dark:text-gray-600">—</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($appointment->notes)
                <div class="mt-8 border-t border-gray-100 dark:border-gray-800 pt-8">
                    <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-3">Note</h2>
                    <p class="whitespace-pre-line rounded bg-gray-50 dark:bg-gray-800 p-4 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $appointment->notes }}</p>
                </div>
            @endif
        </div>

        @if($showPreferencePrompt ?? false)
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm p-5">
            <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                Vuoi salvare il {{ $prefillPreferences['label'] }} come preferenza?
            </p>
            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 mb-4">
                Ti suggeriremo slot simili per i prossimi appuntamenti.
            </p>
            <div class="flex gap-3">
                <form method="POST" action="{{ route('portal.settings.booking-preferences') }}">
                    @csrf
                    @method('PATCH')
                    @foreach($prefillPreferences['preferred_days'] as $d)
                        <input type="hidden" name="preferred_days[]" value="{{ $d }}">
                    @endforeach
                    <input type="hidden" name="preferred_time_from" value="{{ $prefillPreferences['preferred_time_from'] }}">
                    <input type="hidden" name="preferred_time_to"   value="{{ $prefillPreferences['preferred_time_to'] }}">
                    <button type="submit" class="btn-primary rounded px-4 py-2 text-sm font-semibold text-white">
                        Salva preferenza
                    </button>
                </form>
                <form method="POST" action="{{ route('portal.settings.booking-preferences.dismiss') }}">
                    @csrf
                    <button type="submit"
                        class="rounded-md border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                        No, grazie
                    </button>
                </form>
            </div>
        </div>
        @endif

        <aside class="space-y-3">
            @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                <a href="{{ route('portal.appointments.payment', $appointment) }}" class="btn-primary block rounded-md px-5 py-3 text-sm font-semibold text-center text-white">Completa pagamento</a>
            @endif

            @if ($appointment->canBeCancelled())
                <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                    <form method="POST" action="{{ route('portal.appointments.cancel', $appointment) }}">
                        @csrf
                        <div class="flex items-baseline justify-between mb-2">
                            <label for="reason" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Motivo cancellazione <span class="font-normal text-gray-400">(opzionale)</span></label>
                            <span class="text-xs text-gray-400 dark:text-gray-500" x-text="(reason ?? '').length + ' / 1000'"></span>
                        </div>
                        <textarea id="reason" name="reason" rows="3" maxlength="1000"
                            x-data="{ reason: '' }" x-model="reason"
                            placeholder="Es. impegno imprevisto, emergenza familiare…"
                            class="block w-full rounded border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm focus:border-gray-900 dark:focus:border-gray-200 focus:outline-none focus:ring-1 focus:ring-gray-900 dark:focus:ring-gray-200"></textarea>
                        <button type="submit"
                            class="mt-3 w-full rounded border border-red-200 dark:border-red-800 px-4 py-2.5 text-sm font-semibold text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950 transition-colors">
                            Cancella prenotazione
                        </button>
                    </form>
                </div>
            @endif

            <a href="{{ route('portal.appointments.index') }}" class="block rounded border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-center text-sm font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">← Tutti gli appuntamenti</a>
        </aside>
    </section>
@endsection
