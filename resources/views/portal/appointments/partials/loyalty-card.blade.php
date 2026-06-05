@php
    $reached = $loyaltyPoints >= $loyaltyThreshold;
    $progress = $loyaltyThreshold > 0 ? min(100, (int) round($loyaltyPoints / $loyaltyThreshold * 100)) : 0;
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
                    Sconto {{ $loyaltyPercentage }}% disponibile
                </span>
            @endif
        </div>

        <div>
            <div class="h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800" role="progressbar" aria-valuenow="{{ $progress }}" aria-valuemin="0" aria-valuemax="100" aria-label="Avanzamento punti fedeltà">
                <div class="h-full rounded-full bg-green-500 transition-all" style="width: {{ $progress }}%"></div>
            </div>
            @if ($reached)
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Lo sconto verrà applicato in salone al tuo prossimo appuntamento.</p>
            @else
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Ti mancano {{ $loyaltyThreshold - $loyaltyPoints }} punti per uno sconto del {{ $loyaltyPercentage }}%.</p>
            @endif
        </div>
    </div>
</section>
