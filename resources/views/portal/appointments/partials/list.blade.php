<section class="overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm">
    <div class="border-b border-gray-200 dark:border-gray-700 px-5 py-4">
        <h2 class="font-display text-xl font-semibold text-gray-950 dark:text-gray-50">{{ $title }}</h2>
    </div>

    @if ($appointments->isEmpty())
        <div class="flex flex-col items-center gap-3 px-5 py-10 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $empty }}</p>
            @isset($cta)
                <a href="{{ $cta['href'] }}" class="btn-primary inline-block rounded-md px-4 py-2 text-sm font-semibold text-white">{{ $cta['label'] }}</a>
            @endisset
        </div>
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
                                    @if ($appointment->status === 'completed' && in_array($appointment->id, $reviewedAppointmentIds ?? []))
                                        <span class="rounded bg-green-50 dark:bg-green-900/20 px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-400">Recensita ✓</span>
                                    @elseif ($appointment->status === 'completed')
                                        <div x-data="{ open: false, rating: 0, hovered: 0 }">
                                            <button @click="open = true" type="button" class="rounded border border-amber-300 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-50 transition-colors dark:border-amber-700 dark:text-amber-400 dark:hover:bg-amber-900/20">
                                                Recensisci
                                            </button>
                                            <div x-show="open" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" style="display:none">
                                                <div @click.outside="open = false" class="w-full max-w-md rounded-xl bg-white dark:bg-gray-900 shadow-xl p-6">
                                                    <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Lascia una recensione</h3>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-5">{{ $appointment->services_label }} — {{ $appointment->scheduled_date->format('d/m/Y') }}</p>
                                                    <form method="POST" action="{{ route('portal.reviews.store') }}">
                                                        @csrf
                                                        <input type="hidden" name="appointment_id" value="{{ $appointment->id }}">
                                                        <div class="mb-4">
                                                            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">Valutazione</p>
                                                            <div class="flex gap-1">
                                                                @for ($i = 1; $i <= 5; $i++)
                                                                    <label class="cursor-pointer">
                                                                        <input type="radio" name="rating" value="{{ $i }}" style="position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);border:0" x-on:change="rating = {{ $i }}" required>
                                                                        <svg @mouseenter="hovered = {{ $i }}" @mouseleave="hovered = 0"
                                                                            class="w-8 h-8 transition-colors"
                                                                            :class="(hovered || rating) >= {{ $i }} ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'"
                                                                            fill="currentColor" viewBox="0 0 20 20">
                                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                                        </svg>
                                                                    </label>
                                                                @endfor
                                                            </div>
                                                        </div>
                                                        <div class="mb-5" x-data="{ body: '' }">
                                                            <div class="flex items-baseline justify-between mb-1">
                                                                <label class="text-xs font-medium text-gray-700 dark:text-gray-300">La tua esperienza</label>
                                                                <span class="text-xs text-gray-400 dark:text-gray-500" x-text="body.length + ' / 1000'"></span>
                                                            </div>
                                                            <textarea name="body" rows="4" required maxlength="1000" x-model="body" placeholder="Raccontaci com'è andata..." class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"></textarea>
                                                        </div>
                                                        <div class="flex gap-3 justify-end">
                                                            <button type="button" @click="open = false" class="rounded border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">Annulla</button>
                                                            <button type="submit" class="btn-primary rounded px-4 py-2 text-sm font-semibold text-white">Invia</button>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
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
