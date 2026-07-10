@php
    $firstTier = $loyaltyAvailableTiers[0] ?? null;
    $nextTier  = $loyaltyNextTier ?? $loyaltyAvailableTiers[1] ?? null;
    $progressTarget = $firstTier['threshold'] ?? $loyaltyAvailableTiers[0]['threshold'] ?? ($nextTier['threshold'] ?? 1);
    $reached  = $firstTier !== null;
    $progress = $progressTarget > 0 ? min(100, (int) round($loyaltyPoints / $progressTarget * 100)) : 0;
@endphp
<section class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">Programma fedeltà</h2>
    </div>
    <div class="px-5 py-5 space-y-4">
        <div class="flex items-end justify-between">
            <div>
                <p class="text-3xl font-semibold text-gray-950 dark:text-gray-50">{{ $loyaltyPoints }}</p>
                <p class="text-sm text-gray-500 dark:text-gray-400">punti accumulati</p>
            </div>
            @if ($reached)
                <span class="rounded-full bg-green-100 dark:bg-green-900/40 px-3 py-1 text-sm font-semibold text-green-700 dark:text-green-300">
                    Sconto disponibile
                </span>
            @endif
        </div>

        <div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avanzamento punti fedeltà">
                <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $progress }}%"></div>
            </div>
            @if ($reached)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Sconti disponibili! Scegli al prossimo appuntamento.</p>
            @elseif ($nextTier)
                @php
                    $nextLabel = '';
                    if (! empty($nextTier['percentage'])) {
                        $nextLabel = 'uno sconto del ' . $nextTier['percentage'] . '%';
                    } elseif (! empty($nextTier['amount'])) {
                        $nextLabel = 'uno sconto di ' . number_format((float) $nextTier['amount'], 2, ',', '.') . '€';
                    }
                @endphp
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    Ancora {{ $nextTier['threshold'] - $loyaltyPoints }} punti per sbloccare {{ $nextLabel }}.
                </p>
            @else
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Continua a prenotare per sbloccare sconti fedeltà.</p>
            @endif
        </div>

        @if (! empty($loyaltyTiers))
            <div class="border-t border-gray-100 dark:border-gray-800 pt-3">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-400 dark:text-gray-500 mb-2">Livelli premio</p>
                <div class="space-y-1.5">
                    @foreach ($loyaltyTiers as $tier)
                        @php
                            $unlocked = $loyaltyPoints >= (int) ($tier['threshold'] ?? 0);
                            $label = (int) ($tier['threshold'] ?? 0) . ' punti · ';
                            if (! empty($tier['percentage'])) {
                                $label .= (int) $tier['percentage'] . '% sconto';
                            } elseif (! empty($tier['amount'])) {
                                $label .= number_format((float) $tier['amount'], 2, ',', '.') . '€ sconto';
                            }
                        @endphp
                        <div class="flex items-center justify-between text-sm {{ $unlocked ? 'text-green-700 dark:text-green-300' : 'text-gray-500 dark:text-gray-400' }}">
                            <span>{{ $label }}</span>
                            @if ($unlocked)
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>