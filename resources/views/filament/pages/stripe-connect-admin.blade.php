@php
    $hasFees     = $this->hasFees();
    $headerStats = $this->getHeaderStats();
    $accounts    = $this->getAccounts();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">

        {{-- Filtri --}}
        <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex flex-wrap gap-5 items-start">

                {{-- Periodo --}}
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Periodo</p>
                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 divide-x divide-gray-200 dark:divide-gray-700 overflow-hidden text-xs font-medium">
                        @foreach(['today' => 'Oggi', '7d' => '7 gg', '30d' => '30 gg', 'month' => 'Mese', 'all' => 'Tutto'] as $val => $label)
                            <button wire:click="$set('period', '{{ $val }}')"
                                    class="{{ $period === $val ? 'bg-primary-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60' }} px-3 py-2 transition-colors whitespace-nowrap">
                                {{ $label }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Stato --}}
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Stato account</p>
                    <div class="inline-flex rounded-lg border border-gray-200 dark:border-gray-700 divide-x divide-gray-200 dark:divide-gray-700 overflow-hidden text-xs font-medium">
                        @php
                            $statusOptions = [
                                ''           => ['label' => 'Tutti',    'active' => 'bg-gray-700 dark:bg-gray-600 text-white'],
                                'active'     => ['label' => 'Attivi',   'active' => 'bg-success-600 text-white'],
                                'pending'    => ['label' => 'Pending',  'active' => 'bg-warning-500 text-white'],
                                'restricted' => ['label' => 'Ristretti','active' => 'bg-danger-600 text-white'],
                                'disabled'   => ['label' => 'Disab.',   'active' => 'bg-danger-900 text-white'],
                            ];
                        @endphp
                        @foreach($statusOptions as $val => $cfg)
                            <button wire:click="$set('statusFilter', '{{ $val }}')"
                                    class="{{ $statusFilter === $val ? $cfg['active'] : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700/60' }} px-3 py-2 transition-colors whitespace-nowrap">
                                {{ $cfg['label'] }}
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Toggle pills --}}
                <div>
                    <p class="text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest mb-2">Mostra solo</p>
                    <div class="flex gap-2 flex-wrap">
                        <button wire:click="$toggle('problemsOnly')"
                                class="{{ $problemsOnly ? 'bg-danger-50 dark:bg-danger-950 text-danger-700 dark:text-danger-300 border-danger-400 ring-1 ring-danger-300' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-gray-300' }} inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-medium transition-all">
                            <svg class="w-3.5 h-3.5 {{ $problemsOnly ? 'text-danger-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                            </svg>
                            Con problemi
                        </button>
                        <button wire:click="$toggle('withPaymentsOnly')"
                                class="{{ $withPaymentsOnly ? 'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300 border-primary-400 ring-1 ring-primary-300' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-gray-300' }} inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-medium transition-all">
                            <svg class="w-3.5 h-3.5 {{ $withPaymentsOnly ? 'text-primary-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                            </svg>
                            Con pagamenti
                        </button>
                        @if($hasFees)
                        <button wire:click="$toggle('feeOverrideOnly')"
                                class="{{ $feeOverrideOnly ? 'bg-warning-50 dark:bg-warning-950 text-warning-700 dark:text-warning-300 border-warning-400 ring-1 ring-warning-300' : 'bg-white dark:bg-gray-800 text-gray-500 dark:text-gray-400 border-gray-200 dark:border-gray-700 hover:border-gray-300' }} inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border text-xs font-medium transition-all">
                            <svg class="w-3.5 h-3.5 {{ $feeOverrideOnly ? 'text-warning-500' : 'text-gray-400' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Fee personalizzata
                        </button>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        {{-- Statistiche header --}}
        @php
            $cardCount = 4 + ($hasFees ? 1 : 0) + ($headerStats['problem_salons'] > 0 ? 1 : 0);
            $gridClass = match(true) {
                $cardCount >= 6 => 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3',
                $cardCount === 5 => 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3',
                default         => 'grid grid-cols-2 lg:grid-cols-4 gap-3',
            };
        @endphp
        <div class="{{ $gridClass }}">

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-blue-500 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest truncate">Online</p>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">€ {{ number_format($headerStats['volume_online'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $headerStats['payments_online'] }} pag. Stripe</p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-gray-400 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest truncate">Offline</p>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">€ {{ number_format($headerStats['volume_offline'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">{{ $headerStats['payments_offline'] }} pag. in presenza</p>
            </div>

            @if($hasFees)
            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-green-500 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest truncate">Fee Athomos</p>
                </div>
                <p class="text-xl font-bold text-green-600 dark:text-green-400">€ {{ number_format($headerStats['fee'], 2) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">commissioni nel periodo</p>
            </div>
            @endif

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-indigo-500 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest truncate">Pagamenti</p>
                </div>
                <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $headerStats['payments_online'] + $headerStats['payments_offline'] }}</p>
                <p class="text-xs text-gray-400 mt-0.5">totale nel periodo</p>
            </div>

            <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-700 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-sky-500 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-widest truncate">Saloni attivi</p>
                </div>
                <p class="text-xl font-bold text-sky-600 dark:text-sky-400">{{ $headerStats['active_salons'] }}</p>
                <p class="text-xs text-gray-400 mt-0.5">con Stripe Connect</p>
            </div>

            @if($headerStats['problem_salons'] > 0)
            <div class="bg-red-50 dark:bg-red-950 rounded-xl border border-red-200 dark:border-red-800 p-4">
                <div class="flex items-center gap-1.5 mb-2">
                    <div class="w-2 h-2 rounded-full bg-red-500 shrink-0"></div>
                    <p class="text-[10px] font-semibold text-red-400 uppercase tracking-widest truncate">Problemi</p>
                </div>
                <p class="text-xl font-bold text-red-700 dark:text-red-300">{{ $headerStats['problem_salons'] }}</p>
                <p class="text-xs text-red-400 mt-0.5">ristretti o disabilitati</p>
            </div>
            @endif

        </div>

        {{-- Tabella principale --}}
        <x-filament::section heading="Account connessi">
            @if($accounts->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400 py-6 text-center">Nessun account trovato con i filtri selezionati.</p>
            @else
            <div class="overflow-x-auto -mx-6">
                @php $colSpan = 8 + ($hasFees ? 1 : 0); @endphp
                <table class="w-full text-sm min-w-[700px]">
                    <thead>
                        <tr class="text-left text-[10px] font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-widest border-b border-gray-100 dark:border-gray-800">
                            <th class="pb-3 pl-6 pr-3 w-8"></th>
                            <th class="pb-3 pr-4">Salone</th>
                            <th class="pb-3 pr-4">Stato</th>
                            <th class="pb-3 pr-4">Charges / Pay</th>
                            @if($hasFees)
                            <th class="pb-3 pr-4">Fee %</th>
                            @endif
                            <th class="pb-3 pr-4 text-right">Online</th>
                            <th class="pb-3 pr-4 text-right">Offline</th>
                            <th class="pb-3 pr-4">Webhook</th>
                            <th class="pb-3 pr-6"></th>
                        </tr>
                    </thead>
                    @foreach ($accounts as $account)
                    <tbody x-data="{ expanded: false }">
                        <tr class="border-b border-gray-100 dark:border-gray-800/60 hover:bg-gray-50/60 dark:hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 pl-6 pr-3">
                                <button @click="expanded = !expanded"
                                        class="text-gray-300 dark:text-gray-600 hover:text-gray-500 dark:hover:text-gray-300 transition-all duration-150"
                                        :class="{ 'rotate-90 !text-gray-500 dark:!text-gray-300': expanded }">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </button>
                            </td>
                            <td class="py-3 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                {{ $account->business?->name ?? '—' }}
                            </td>
                            <td class="py-3 pr-4">
                                @php
                                    $color = match($account->status) {
                                        'active'     => 'success',
                                        'restricted' => 'danger',
                                        'disabled'   => 'danger',
                                        default      => 'warning',
                                    };
                                @endphp
                                <x-filament::badge :color="$color" size="sm">{{ $account->status }}</x-filament::badge>
                            </td>
                            <td class="py-3 pr-4">
                                <div class="flex gap-1">
                                    <x-filament::badge :color="$account->charges_enabled ? 'success' : 'gray'" size="sm">chr {{ $account->charges_enabled ? '✓' : '✗' }}</x-filament::badge>
                                    <x-filament::badge :color="$account->payouts_enabled ? 'success' : 'gray'" size="sm">pay {{ $account->payouts_enabled ? '✓' : '✗' }}</x-filament::badge>
                                </div>
                            </td>
                            @if($hasFees)
                            <td class="py-3 pr-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $account->effective_fee_percent }}%
                                @if($account->has_fee_override)
                                    <x-filament::badge color="warning" size="sm" class="ml-0.5">custom</x-filament::badge>
                                @endif
                            </td>
                            @endif
                            <td class="py-3 pr-4 text-right">
                                <p class="font-mono text-sm font-medium text-gray-800 dark:text-gray-200">€ {{ number_format($account->stats_volume, 2) }}</p>
                                @if($account->stats_count > 0)
                                <p class="text-[10px] text-blue-500 dark:text-blue-400 mt-0.5">{{ $account->stats_count }} pag.</p>
                                @endif
                                @if($hasFees && $account->stats_fee > 0)
                                <p class="text-[10px] text-green-600 dark:text-green-400 font-mono">fee € {{ number_format($account->stats_fee, 2) }}</p>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-right">
                                <p class="font-mono text-sm text-gray-500 dark:text-gray-400">€ {{ number_format($account->stats_volume_offline, 2) }}</p>
                                @if($account->stats_count_offline > 0)
                                <p class="text-[10px] text-gray-400 mt-0.5">{{ $account->stats_count_offline }} pag.</p>
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-xs text-gray-400 dark:text-gray-500">
                                {{ $account->last_webhook_at?->diffForHumans() ?? 'Mai' }}
                            </td>
                            <td class="py-3 pr-6">
                                <button
                                    wire:click="syncAccount({{ $account->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="syncAccount({{ $account->id }})"
                                    class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 disabled:opacity-40 whitespace-nowrap">
                                    <span wire:loading.remove wire:target="syncAccount({{ $account->id }})">Sincronizza</span>
                                    <span wire:loading wire:target="syncAccount({{ $account->id }})">...</span>
                                </button>
                            </td>
                        </tr>
                        {{-- Riga dettagli tecnici --}}
                        <tr x-show="expanded" x-cloak>
                            <td colspan="{{ $colSpan }}" class="pl-14 pr-6 pb-4 pt-0">
                                <div class="bg-gray-50 dark:bg-gray-800/40 rounded-lg p-4">
                                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4 text-xs">
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Account ID</p>
                                            <p class="font-mono text-gray-700 dark:text-gray-300 break-all">{{ $account->stripe_account_id ?? '—' }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Paese / Valuta</p>
                                            <p class="text-gray-700 dark:text-gray-300">
                                                {{ strtoupper($account->country ?? '—') }} / {{ strtoupper($account->default_currency ?? '—') }}
                                            </p>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Dettagli inviati</p>
                                            <p class="text-gray-700 dark:text-gray-300">{{ $account->details_submitted ? 'Sì' : 'No' }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Onboarding</p>
                                            <p class="text-gray-700 dark:text-gray-300">{{ $account->onboarding_completed_at?->format('d/m/Y H:i') ?? 'Non completato' }}</p>
                                        </div>
                                        @if($account->capabilities)
                                        <div class="col-span-2">
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Capabilities</p>
                                            <p class="text-gray-700 dark:text-gray-300">
                                                {{ collect($account->capabilities)->map(fn($v, $k) => "$k: $v")->implode(' · ') }}
                                            </p>
                                        </div>
                                        @endif
                                        @if($account->requirements_currently_due && count($account->requirements_currently_due))
                                        <div class="col-span-2">
                                            <p class="font-semibold text-orange-500 uppercase tracking-wide mb-1">Currently due</p>
                                            <p class="text-orange-700 dark:text-orange-300">{{ implode(', ', $account->requirements_currently_due) }}</p>
                                        </div>
                                        @endif
                                        @if($account->requirements_past_due && count($account->requirements_past_due))
                                        <div class="col-span-2">
                                            <p class="font-semibold text-red-500 uppercase tracking-wide mb-1">Past due</p>
                                            <p class="text-red-700 dark:text-red-300">{{ implode(', ', $account->requirements_past_due) }}</p>
                                        </div>
                                        @endif
                                        @if($account->requirements_disabled_reason)
                                        <div class="col-span-2 md:col-span-3 lg:col-span-4">
                                            <p class="font-semibold text-red-500 uppercase tracking-wide mb-1">Motivo disabilitazione</p>
                                            <p class="text-red-700 dark:text-red-400">{{ $account->requirements_disabled_reason }}</p>
                                        </div>
                                        @endif
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Creato il</p>
                                            <p class="text-gray-700 dark:text-gray-300">{{ $account->created_at->format('d/m/Y H:i') }}</p>
                                        </div>
                                        <div>
                                            <p class="font-semibold text-gray-400 dark:text-gray-500 uppercase tracking-wide mb-1">Ultimo webhook</p>
                                            <p class="text-gray-700 dark:text-gray-300">{{ $account->last_webhook_at?->format('d/m/Y H:i') ?? 'Mai' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                    @endforeach
                </table>
            </div>
            @endif
        </x-filament::section>

        {{-- Configurazione fee (solo se attiva) --}}
        @if($hasFees)
        <x-filament::section heading="Configurazione fee">
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Fee globale: <strong>{{ config('services.stripe.platform_fee_percent', 0) }}%</strong>
                <span class="text-xs text-gray-400 ml-1">(env <code>STRIPE_PLATFORM_FEE_PERCENT</code>)</span><br>
                Sovrascrivibile per singolo salone tramite <code class="text-xs">businesses.stripe_platform_fee_percent</code>.
            </p>
        </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
