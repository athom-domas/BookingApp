@extends('layouts.app')

@section('title', 'Dettaglio appuntamento')

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-950 dark:text-gray-50">{{ $appointment->services_label }}</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</p>
                </div>
                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
            </div>

            <dl class="mt-8 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Staff</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->staff->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Durata</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ $appointment->services->sum('duration_minutes') }} min</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Prezzo</dt>
                    <dd class="mt-1 text-base text-gray-950 dark:text-gray-50">{{ number_format((float) $appointment->final_price, 2, ',', '.') }} euro</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600 dark:text-gray-400">Pagamento</dt>
                    <dd class="mt-1">
                        @if ($appointment->payment)
                            @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                        @else
                            <span class="text-sm text-gray-500 dark:text-gray-500">Nessun pagamento</span>
                        @endif
                    </dd>
                </div>
            </dl>

            @if ($appointment->notes)
                <div class="mt-8">
                    <h2 class="text-sm font-medium text-gray-600 dark:text-gray-400">Note</h2>
                    <p class="mt-2 whitespace-pre-line rounded-md bg-gray-50 dark:bg-gray-800 p-4 text-sm leading-6 text-gray-700 dark:text-gray-300">{{ $appointment->notes }}</p>
                </div>
            @endif
        </div>

        <aside class="space-y-4">
            @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                <a href="{{ route('portal.appointments.payment', $appointment) }}" class="block rounded-md px-6 py-3 text-sm font-semibold text-white shadow-sm" style="background-color: var(--color-primary)">Completa pagamento</a>
            @endif

            @if ($appointment->canBeCancelled())
                <form method="POST" action="{{ route('portal.appointments.cancel', $appointment) }}" class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm">
                    @csrf
                    <label for="reason" class="block text-sm font-medium text-gray-900 dark:text-gray-200">Motivo cancellazione</label>
                    <textarea id="reason" name="reason" rows="3" maxlength="1000" class="mt-2 block w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-950 dark:text-gray-50 px-3 py-2 text-sm shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100 dark:focus:ring-blue-900"></textarea>
                    <button type="submit" class="mt-4 w-full rounded-md border border-red-300 dark:border-red-700 px-4 py-3 text-sm font-semibold text-red-700 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-950">
                        Cancella prenotazione
                    </button>
                </form>
            @endif

            <a href="{{ route('portal.appointments.index') }}" class="block rounded-md border border-gray-300 dark:border-gray-600 px-4 py-3 text-center text-sm font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Torna agli appuntamenti</a>
        </aside>
    </section>
@endsection
