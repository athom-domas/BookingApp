<section class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">{{ $title }}</h2>
    </div>

    @if ($appointments->isEmpty())
        <p class="px-5 py-8 text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800/60 text-left">
                    <tr>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Servizio</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Staff</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Data</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Stato</th>
                        <th class="px-5 py-3 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Pagamento</th>
                        <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800 bg-white dark:bg-gray-900">
                    @foreach ($appointments as $appointment)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40 transition-colors duration-100">
                            <td class="px-5 py-4 font-semibold text-gray-950 dark:text-gray-50">{{ $appointment->services_label }}</td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    @php $avatarUrl = $appointment->staff->getFirstMediaUrl('avatar', 'thumb'); @endphp
                                    @if ($avatarUrl)
                                        <img src="{{ $avatarUrl }}" alt="{{ $appointment->staff->name }}" class="w-7 h-7 rounded-full object-cover shrink-0">
                                    @else
                                        <span class="inline-flex w-7 h-7 rounded-full bg-gray-100 dark:bg-gray-800 items-center justify-center text-xs font-semibold text-gray-500 dark:text-gray-400 shrink-0">{{ strtoupper(mb_substr($appointment->staff->name, 0, 1)) }}</span>
                                    @endif
                                    <span class="text-gray-600 dark:text-gray-400">{{ $appointment->staff->name }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-4 tabular-nums text-gray-600 dark:text-gray-400">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
                            </td>
                            <td class="px-5 py-4">
                                @if ($appointment->payment)
                                    @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                                @else
                                    <span class="rounded bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-xs font-medium text-gray-500 dark:text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('portal.appointments.show', $appointment) }}" class="rounded border border-gray-200 dark:border-gray-700 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Dettaglio</a>
                                    @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                                        <a href="{{ route('portal.appointments.payment', $appointment) }}" class="btn-primary rounded px-3 py-1.5 text-xs font-semibold text-white">Paga</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
