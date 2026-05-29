<x-filament-panels::page>
    @php
        $business = $this->getBusiness();
        $status = $business->subscriptionStatus();
        $checkoutPending = request()->query('checkout') === 'success' && $status !== 'active';
        $daysLeft = $business->trial_ends_at?->isFuture()
            ? (int) now()->diffInDays($business->trial_ends_at)
            : 0;
    @endphp

    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Piano GestionalePro</x-slot>

            @if ($checkoutPending)
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="info">Attivazione in corso</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Il pagamento è stato ricevuto. L'abbonamento sarà attivo entro pochi secondi.
                        <a href="{{ url()->current() }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">
                            Ricarica la pagina
                        </a>
                    </span>
                </div>

            @elseif ($status === 'trial')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="warning">Periodo di prova</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Il tuo periodo di prova termina il
                        <strong>{{ $business->trial_ends_at->format('d/m/Y') }}</strong>
                        ({{ $daysLeft }} {{ $daysLeft === 1 ? 'giorno rimasto' : 'giorni rimasti' }})
                    </span>
                </div>

            @elseif ($status === 'active')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="success">Piano attivo</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        GestionalePro — €29/mese
                    </span>
                </div>
                @if ($business->subscription('default')?->current_period_end)
                    <p class="mt-2 text-sm text-gray-500">
                        Prossimo rinnovo: <strong>{{ \Carbon\Carbon::createFromTimestamp($business->subscription('default')->asStripeSubscription()->current_period_end)->format('d/m/Y') }}</strong>
                    </p>
                @endif
                @if ($business->pm_last_four)
                    <p class="mt-1 text-sm text-gray-500">
                        Metodo di pagamento: {{ ucfirst($business->pm_type ?? '') }} ••••{{ $business->pm_last_four }}
                    </p>
                @endif

            @elseif ($status === 'grace_period')
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="warning">Abbonamento annullato</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Accesso garantito fino al
                        <strong>{{ $business->subscription('default')->ends_at?->format('d/m/Y') }}</strong>
                    </span>
                </div>

            @else
                <div class="flex items-center gap-3 flex-wrap">
                    <x-filament::badge color="danger">Accesso scaduto</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">
                        Il periodo di prova è terminato. Abbonati per continuare a usare GestionalePro.
                    </span>
                </div>
            @endif
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Dettagli piano</x-slot>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">GestionalePro</p>
                    <p class="text-gray-500">Piano unico</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">€29/mese</p>
                    <p class="text-gray-500">IVA esclusa</p>
                </div>
                <div>
                    <p class="font-semibold text-gray-900 dark:text-white">Cancellazione</p>
                    @if ($status === 'active' && auth()->user()?->isAdmin())
                        <button wire:click="mountAction('cancel')"
                                class="text-sm text-red-600 dark:text-red-400 hover:underline font-medium mt-0.5">
                            Annulla abbonamento
                        </button>
                    @elseif ($status === 'grace_period')
                        <p class="text-gray-500">Annullato — accesso fino al {{ $business->subscription('default')?->ends_at?->format('d/m/Y') }}</p>
                    @else
                        <p class="text-gray-500">In qualsiasi momento</p>
                    @endif
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
