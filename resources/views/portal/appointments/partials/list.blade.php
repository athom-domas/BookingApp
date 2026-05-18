<section class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="text-lg font-semibold text-gray-950 dark:text-gray-50">{{ $title }}</h2>
    </div>

    @if ($appointments->isEmpty())
        <p class="px-5 py-6 text-sm text-gray-600 dark:text-gray-400">{{ $empty }}</p>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                <thead class="bg-gray-50 dark:bg-gray-800 text-left text-xs font-semibold uppercase tracking-normal text-gray-600 dark:text-gray-400">
                    <tr>
                        <th class="px-5 py-3">Servizio</th>
                        <th class="px-5 py-3">Staff</th>
                        <th class="px-5 py-3">Data</th>
                        <th class="px-5 py-3">Stato</th>
                        <th class="px-5 py-3">Pagamento</th>
                        <th class="px-5 py-3 text-right">Azioni</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-900">
                    @foreach ($appointments as $appointment)
                        <tr>
                            <td class="px-5 py-4 font-medium text-gray-950 dark:text-gray-50">{{ $appointment->services_label }}</td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">{{ $appointment->staff->name }}</td>
                            <td class="px-5 py-4 text-gray-700 dark:text-gray-300">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</td>
                            <td class="px-5 py-4">
                                @include('portal.appointments.partials.status-badge', ['status' => $appointment->status])
                            </td>
                            <td class="px-5 py-4">
                                @if ($appointment->payment)
                                    @include('portal.appointments.partials.payment-badge', ['status' => $appointment->payment->status])
                                @else
                                    <span class="rounded-md bg-gray-100 dark:bg-gray-800 px-2 py-1 text-xs font-medium text-gray-700 dark:text-gray-300">Assente</span>
                                @endif
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('portal.appointments.show', $appointment) }}" class="rounded-md border border-gray-300 dark:border-gray-600 px-3 py-2 text-xs font-semibold text-gray-900 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">Dettaglio</a>
                                    @if ($appointment->payment && $appointment->payment->status !== 'completed' && $appointment->status !== 'cancelled')
                                        <a href="{{ route('portal.appointments.payment', $appointment) }}" class="rounded-md bg-blue-700 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-800">Paga</a>
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
