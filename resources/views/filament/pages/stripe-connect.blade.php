<x-filament-panels::page>
    @php $state = $this->getUiState(); $account = $this->getConnectAccount(); @endphp

    <div class="max-w-2xl space-y-6">

        @if ($state === 'not_connected')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <x-slot name="description">Collega il tuo account Stripe per accettare pagamenti online dai clienti.</x-slot>

                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <x-filament::badge color="gray">Non configurato</x-filament::badge>
                    </div>
                    <ol class="space-y-2 text-sm text-gray-600 dark:text-gray-400 list-decimal list-inside">
                        <li>Clicca "Collega Stripe" e completa la verifica guidata (~5 minuti)</li>
                        <li>Stripe verifica i tuoi dati — di solito poche ore</li>
                        <li>I pagamenti online si attivano automaticamente</li>
                    </ol>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Athomos trattiene il {{ number_format($this->getEffectiveFeePercent(), 1) }}% come commissione su ogni pagamento online.<br>
                        Finché non configuri Stripe, i clienti possono prenotare solo con pagamento in salone.
                    </p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.start') }}" icon="heroicon-o-arrow-right">
                        Collega Stripe
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'incomplete')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="warning">Configurazione incompleta</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Hai avviato la configurazione ma non l'hai completata. Clicca per riprendere dal punto in cui ti sei fermato.</p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.refresh') }}" icon="heroicon-o-arrow-path">
                        Riprendi configurazione
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'pending_review')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="info">In attesa di approvazione</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Hai completato il modulo. Stripe sta verificando i tuoi dati — di solito richiede poche ore.
                        Riceverai una notifica appena i pagamenti online saranno attivi.
                    </p>
                </div>
            </x-filament::section>

        @elseif ($state === 'active')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="success">Attivo</x-filament::badge>
                    <dl class="text-sm space-y-1">
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Account:</dt>
                            <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $account->stripe_account_id }}</dd>
                        </div>
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Commissione piattaforma:</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ number_format($this->getEffectiveFeePercent(), 1) }}%</dd>
                        </div>
                        @if ($account->onboarding_completed_at)
                        <div class="flex gap-2">
                            <dt class="text-gray-500 dark:text-gray-400">Attivo dal:</dt>
                            <dd class="text-gray-900 dark:text-gray-100">{{ $account->onboarding_completed_at->format('d/m/Y') }}</dd>
                        </div>
                        @endif
                    </dl>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.dashboard') }}" icon="heroicon-o-arrow-top-right-on-square" color="gray" outlined>
                        Gestisci account Stripe
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'restricted')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="danger">Account sospeso</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        Stripe richiede ulteriori informazioni per mantenere attivo il tuo account.
                        Clicca per accedere e risolvere i requisiti mancanti.
                    </p>
                    <x-filament::button tag="a" href="{{ route('stripe.connect.refresh') }}" icon="heroicon-o-exclamation-triangle" color="danger">
                        Risolvi su Stripe
                    </x-filament::button>
                </div>
            </x-filament::section>

        @elseif ($state === 'disabled')
            <x-filament::section>
                <x-slot name="heading">Pagamenti online</x-slot>
                <div class="space-y-4">
                    <x-filament::badge color="danger">Account disabilitato</x-filament::badge>
                    <p class="text-sm text-gray-600 dark:text-gray-400">L'account Stripe è stato disabilitato. Contatta il supporto Athomos per assistenza.</p>
                </div>
            </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
