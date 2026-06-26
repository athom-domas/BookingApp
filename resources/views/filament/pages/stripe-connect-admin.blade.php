<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section heading="Account connessi">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                            <th class="pb-2 pr-4">Salone</th>
                            <th class="pb-2 pr-4">Account ID</th>
                            <th class="pb-2 pr-4">Stato</th>
                            <th class="pb-2 pr-4">Charges</th>
                            <th class="pb-2 pr-4">Ultimo webhook</th>
                            <th class="pb-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($this->getAccounts() as $account)
                        <tr>
                            <td class="py-2 pr-4 font-medium text-gray-900 dark:text-gray-100">
                                {{ $account->business?->name ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-600 dark:text-gray-400">
                                {{ $account->stripe_account_id ?? '—' }}
                            </td>
                            <td class="py-2 pr-4">
                                @php
                                    $color = match($account->status) {
                                        'active'     => 'success',
                                        'restricted' => 'danger',
                                        'disabled'   => 'danger',
                                        default      => 'warning',
                                    };
                                @endphp
                                <x-filament::badge :color="$color">{{ $account->status }}</x-filament::badge>
                            </td>
                            <td class="py-2 pr-4">
                                <x-filament::badge :color="$account->charges_enabled ? 'success' : 'gray'">
                                    {{ $account->charges_enabled ? 'Sì' : 'No' }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">
                                {{ $account->last_webhook_at?->diffForHumans() ?? 'Mai' }}
                            </td>
                            <td class="py-2">
                                <button
                                    wire:click="syncAccount({{ $account->id }})"
                                    class="text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400">
                                    Sincronizza
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Fee piattaforma globale">
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Fee attuale: <strong>{{ config('services.stripe.platform_fee_percent', 2.5) }}%</strong> (env <code>STRIPE_PLATFORM_FEE_PERCENT</code>)<br>
                Sovrascrivibile per singolo salone tramite <code>businesses.stripe_platform_fee_percent</code>.
            </p>
            <p class="text-sm text-gray-500">
                Totale commissioni incassate:
                <strong>€ {{ number_format(\App\Models\Payment::withoutGlobalScopes()->sum('platform_fee_amount'), 2) }}</strong>
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
