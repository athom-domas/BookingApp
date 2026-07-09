<x-filament-panels::page>
    @php
        $business   = $this->getBusiness();
        $status     = $business->subscriptionStatus();
        $sub        = $business->subscription('default');
        $checkoutPending = request()->query('checkout') === 'success' && $status !== 'active';

        $daysLeft = $business->trial_ends_at?->isFuture()
            ? (int) now()->diffInDays($business->trial_ends_at)
            : 0;
        $daysPassed     = max(0, 14 - $daysLeft);
        $trialProgress  = min(100, (int) ($daysPassed / 14 * 100));

        $stripeData = null;
        if ($status === 'active' && $sub) {
            try { $stripeData = $sub->asStripeSubscription(); } catch (\Exception) {}
        }

        $renewalDate = null;
        if ($stripeData) {
            $periodEnd = $stripeData->items->data[0]->current_period_end
                ?? $stripeData->current_period_end
                ?? null;
            if ($periodEnd) {
                $renewalDate = \Carbon\Carbon::createFromTimestamp($periodEnd);
            }
        }

        $isAdmin = auth()->user()?->isAdmin();
    @endphp

    <div class="space-y-5">

        {{-- ═══════════ STATUS BANNER ═══════════ --}}

        @if ($checkoutPending)
            <div class="rounded-xl border border-blue-200 bg-blue-50 dark:bg-blue-950/60 dark:border-blue-800 p-5 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center shrink-0">
                    <x-heroicon-o-arrow-path class="w-5 h-5 text-blue-600 dark:text-blue-400 animate-spin"/>
                </div>
                <div>
                    <p class="font-semibold text-blue-900 dark:text-blue-100 text-sm">Attivazione in corso</p>
                    <p class="text-sm text-blue-700 dark:text-blue-400 mt-0.5">
                        Il pagamento è stato ricevuto. L'abbonamento sarà attivo tra qualche secondo. &nbsp;
                        <a href="{{ url()->current() }}" class="underline font-medium">Ricarica la pagina</a>
                    </p>
                </div>
            </div>

        @elseif ($status === 'trial')
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/60 dark:border-amber-800 p-6">
                <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center shrink-0">
                            <x-heroicon-o-clock class="w-5 h-5 text-amber-600 dark:text-amber-400"/>
                        </div>
                        <div>
                            <p class="font-semibold text-amber-900 dark:text-amber-100">Periodo di prova attivo</p>
                            <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">
                                Termina il <strong>{{ $business->trial_ends_at->format('d/m/Y') }}</strong>
                                — {{ $daysLeft }} {{ $daysLeft === 1 ? 'giorno rimasto' : 'giorni rimasti' }}
                            </p>
                            <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">
                                Durante il trial hai accesso completo al piano <strong>Plus</strong>. Alla scadenza dovrai scegliere un piano per continuare.
                            </p>
                        </div>
                    </div>
                </div>
                <div class="space-y-1">
                    <div class="flex justify-between text-xs text-amber-600 dark:text-amber-500">
                        <span>Inizio prova</span>
                        <span>{{ $daysLeft }} gg rimasti</span>
                    </div>
                    <div class="h-1.5 rounded-full bg-amber-200 dark:bg-amber-800 overflow-hidden">
                        <div class="h-full rounded-full bg-amber-500 dark:bg-amber-400 transition-all duration-500" style="width: {{ $trialProgress }}%"></div>
                    </div>
                </div>
            </div>

        @elseif ($status === 'active')
            <div class="rounded-xl border border-green-200 bg-green-50 dark:bg-green-950/60 dark:border-green-800 p-6">
                <div class="flex items-center gap-3 mb-5">
                    <div class="w-10 h-10 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center shrink-0">
                        <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400"/>
                    </div>
                    <div>
                        <p class="font-semibold text-green-900 dark:text-green-100">Piano attivo — {{ ucfirst($business->plan ?? 'base') }}</p>
                        <p class="text-sm text-green-700 dark:text-green-400 mt-0.5">Piano {{ ucfirst($business->plan ?? 'base') }} · IVA esclusa · Cancellazione in qualsiasi momento</p>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-{{ $renewalDate ? '3' : '2' }} gap-4 pt-4 border-t border-green-200 dark:border-green-800">
                    <div>
                        <p class="text-xs font-medium text-green-600 dark:text-green-500 uppercase tracking-wide">Attivato il</p>
                        <p class="mt-1 text-sm font-semibold text-green-900 dark:text-green-100">{{ $sub->created_at->format('d/m/Y') }}</p>
                    </div>
                    @if ($renewalDate)
                    <div>
                        <p class="text-xs font-medium text-green-600 dark:text-green-500 uppercase tracking-wide">Prossimo rinnovo</p>
                        <p class="mt-1 text-sm font-semibold text-green-900 dark:text-green-100">{{ $renewalDate->format('d/m/Y') }}</p>
                    </div>
                    @endif
                    @if ($business->pm_last_four)
                    <div>
                        <p class="text-xs font-medium text-green-600 dark:text-green-500 uppercase tracking-wide">Pagamento</p>
                        <p class="mt-1 text-sm font-semibold text-green-900 dark:text-green-100">{{ ucfirst($business->pm_type ?? 'Carta') }} ••••{{ $business->pm_last_four }}</p>
                    </div>
                    @endif
                </div>
            </div>

        @elseif ($status === 'grace_period')
            <div class="rounded-xl border border-amber-200 bg-amber-50 dark:bg-amber-950/60 dark:border-amber-800 p-5 flex items-center gap-4">
                <div class="w-10 h-10 rounded-full bg-amber-100 dark:bg-amber-900 flex items-center justify-center shrink-0">
                    <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-amber-600 dark:text-amber-400"/>
                </div>
                <div>
                    <p class="font-semibold text-amber-900 dark:text-amber-100 text-sm">Abbonamento annullato</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">
                        Hai ancora accesso completo fino al <strong>{{ $sub?->ends_at?->format('d/m/Y') }}</strong>.
                        Puoi riattivare l'abbonamento in qualsiasi momento.
                    </p>
                </div>
            </div>

        @else
            <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-950/60 dark:border-red-800 p-8 text-center">
                <div class="w-14 h-14 rounded-full bg-red-100 dark:bg-red-900 flex items-center justify-center mx-auto mb-3">
                    <x-heroicon-o-lock-closed class="w-7 h-7 text-red-500 dark:text-red-400"/>
                </div>
                <p class="font-semibold text-red-900 dark:text-red-100 text-base">Accesso sospeso</p>
                <p class="text-sm text-red-700 dark:text-red-400 mt-1 mb-5">
                    Il periodo di prova è terminato. Attiva l'abbonamento per continuare a usare BookingApp.
                </p>
                @if ($isAdmin)
                    <p class="text-xs text-red-500 dark:text-red-500">Usa i pulsanti <strong>Attiva Base</strong> o <strong>Attiva Plus</strong> qui sotto per scegliere il tuo piano.</p>
                @endif
            </div>
        @endif

        {{-- ═══════════ PIANI ═══════════ --}}
        @if ($isAdmin)
        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            @foreach (['base', 'plus'] as $planKey)
            @php
                $planConfig   = config("plans.{$planKey}");
                $isCurrentPaidPlan = $business->plan === $planKey && $business->subscribed('default');
                $isPlusPlan        = $planKey === 'plus';
            @endphp
            <div class="rounded-xl border {{ $isPlusPlan ? 'border-primary-500 dark:border-primary-400' : 'border-gray-200 dark:border-gray-700' }} bg-white dark:bg-gray-900 p-6 flex flex-col relative">
                @if ($isCurrentPaidPlan)
                    <span class="absolute top-4 right-4 inline-flex items-center rounded-full bg-primary-100 dark:bg-primary-900 px-2.5 py-0.5 text-xs font-medium text-primary-800 dark:text-primary-200">Piano attuale</span>
                @endif

                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">{{ $planConfig['label'] }}</h3>

                <ul class="space-y-2 flex-1 mb-6">
                    @foreach ($planConfig['features'] as $feature)
                    <li class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <x-heroicon-m-check-circle class="w-4 h-4 text-teal-500 shrink-0"/>
                        {{ $feature }}
                    </li>
                    @endforeach
                </ul>

                @if ($isCurrentPaidPlan)
                    <button disabled
                            class="w-full rounded-lg px-4 py-2 text-sm font-semibold {{ $isPlusPlan ? 'bg-primary-100 dark:bg-primary-900 text-primary-700 dark:text-primary-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400' }} cursor-default">
                        Piano attuale
                    </button>
                @elseif (in_array($status, ['trial', 'expired']))
                    <button wire:click="checkoutRedirect('{{ $planKey }}')"
                            class="w-full rounded-lg px-4 py-2 text-sm font-semibold {{ $isPlusPlan ? 'bg-primary-600 hover:bg-primary-700 text-white' : 'bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white' }} transition-colors">
                        Attiva {{ $planConfig['label'] }}@if (!empty($planPrices[$planKey])) · €{{ number_format($planPrices[$planKey] / 100, 0) }}/mese @endif
                    </button>
                @elseif ($status === 'active' && !$isCurrentPaidPlan)
                    @if ($planKey === 'plus')
                        <button wire:click="mountAction('upgradePlus')"
                                class="w-full rounded-lg px-4 py-2 text-sm font-semibold bg-primary-600 hover:bg-primary-700 text-white transition-colors">
                            Passa a Plus
                        </button>
                    @else
                        <button wire:click="mountAction('downgradeBase')"
                                class="w-full rounded-lg px-4 py-2 text-sm font-semibold bg-gray-100 dark:bg-gray-800 hover:bg-gray-200 dark:hover:bg-gray-700 text-gray-900 dark:text-white transition-colors">
                            Torna a Base
                        </button>
                    @endif
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- ═══════════ DETTAGLI + PAGAMENTO (grid) ═══════════ --}}

        @if (in_array($status, ['active', 'grace_period']) || $checkoutPending)
        <div class="grid grid-cols-1 @if($status === 'active' && $business->pm_last_four) lg:grid-cols-2 @endif gap-5">

            {{-- Piano --}}
            <x-filament::section>
                <x-slot name="heading">Dettagli piano</x-slot>
                <dl class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ([
                        ['Piano',         'BookingApp'],
                        ['Tipo',          'Piano mensile'],
                        ['Prezzo',        '€29 / mese (IVA esclusa)'],
                        ['Fatturazione',  'Mensile, con rinnovo automatico'],
                    ] as [$label, $value])
                    <div class="flex justify-between items-center py-2.5 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">{{ $label }}</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right">{{ $value }}</dd>
                    </div>
                    @endforeach

                    @if ($sub && $stripeData?->current_period_start && $stripeData?->current_period_end)
                    <div class="flex justify-between items-center py-2.5 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Periodo attuale</dt>
                        <dd class="font-medium text-gray-900 dark:text-white text-right">
                            {{ \Carbon\Carbon::createFromTimestamp($stripeData->current_period_start)->format('d/m/Y') }}
                            — {{ \Carbon\Carbon::createFromTimestamp($stripeData->current_period_end)->format('d/m/Y') }}
                        </dd>
                    </div>
                    @endif

                    <div class="flex justify-between items-center py-2.5 text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Cancellazione</dt>
                        <dd class="font-medium text-right">
                            @if ($status === 'active' && $isAdmin)
                                <button wire:click="mountAction('cancel')"
                                        class="text-red-600 dark:text-red-400 hover:underline">
                                    Annulla abbonamento
                                </button>
                            @elseif ($status === 'grace_period')
                                <span class="text-amber-600 dark:text-amber-400">Annullata</span>
                            @else
                                <span class="text-gray-900 dark:text-white">In qualsiasi momento</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-filament::section>

            {{-- Metodo di pagamento --}}
            @if ($status === 'active' && $business->pm_last_four)
            <x-filament::section>
                <x-slot name="heading">Metodo di pagamento</x-slot>
                <div class="flex items-center gap-4 py-2">
                    <div class="w-14 h-9 rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex items-center justify-center shrink-0 shadow-sm">
                        <x-heroicon-o-credit-card class="w-5 h-5 text-gray-400"/>
                    </div>
                    <div>
                        <p class="font-semibold text-sm text-gray-900 dark:text-white">
                            {{ ucfirst($business->pm_type ?? 'Carta') }} ••••{{ $business->pm_last_four }}
                        </p>
                        @if ($business->pm_expiration)
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Scadenza: {{ $business->pm_expiration }}</p>
                        @endif
                    </div>
                </div>

                @if ($renewalDate)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-white/5 space-y-2.5">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Prossima fattura</span>
                        {{-- Amount shown on Stripe invoice, not hardcoded here. --}}
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-500 dark:text-gray-400">Data addebito</span>
                        <span class="font-medium text-gray-900 dark:text-white">{{ $renewalDate->format('d/m/Y') }}</span>
                    </div>
                </div>
                @endif
            </x-filament::section>
            @endif

        </div>
        @endif

        {{-- ═══════════ FAQ ═══════════ --}}
        <x-filament::section>
            <x-slot name="heading">Domande frequenti</x-slot>
            <div class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ([
                    [
                        'q' => 'Quando vengo addebitato?',
                        'a' => 'Al momento dell\'attivazione dell\'abbonamento. I rinnovi successivi avvengono automaticamente ogni mese alla stessa data.',
                    ],
                    [
                        'q' => 'Posso cancellare in qualsiasi momento?',
                        'a' => 'Sì. Puoi annullare l\'abbonamento quando vuoi dalla pagina Abbonamento. L\'accesso rimane attivo fino alla fine del periodo già pagato — non ci sono penali né costi aggiuntivi.',
                    ],
                    [
                        'q' => 'Cosa succede se passo da Base a Plus?',
                        'a' => "L'upgrade è immediato: hai accesso all'Assistente AI WhatsApp dal momento in cui confermi.\n\nVieni addebitato solo per i giorni rimanenti del ciclo in corso, calcolati proporzionalmente. Esempio: se hai pagato Base (€29) il 1° del mese e passi a Plus (€39) il giorno 16, restano 15 giorni su 30. Stripe ti accredita €14,50 (Base non usato) e addebita €19,50 (Plus per 15 giorni), per un netto di €5. Al rinnovo successivo pagherai l'intero mese Plus.",
                    ],
                    [
                        'q' => 'Cosa succede se torno da Plus a Base?',
                        'a' => "Il downgrade è immediato: l'Assistente AI WhatsApp viene disattivato subito.\n\nStripe ti accredita i giorni non usati del piano Plus sulla prossima fattura. Esempio: hai pagato Plus (€39) il 1° del mese e torni a Base (€29) il giorno 16, restano 15 giorni su 30. Stripe ti accredita €19,50 (Plus non usato) e addebita €14,50 (Base per 15 giorni), scalando €5 dalla fattura successiva.",
                    ],
                    [
                        'q' => 'Cosa succede se un pagamento va a buon fine e poi fallisce?',
                        'a' => 'Stripe riprova il pagamento automaticamente nei giorni successivi (dopo 1, 3, 5 e 7 giorni). Durante i tentativi di recupero l\'accesso alle funzionalità premium viene sospeso cautelativamente. Se tutti i retry falliscono, l\'abbonamento viene cancellato.',
                    ],
                    [
                        'q' => 'Il periodo di prova richiede una carta di credito?',
                        'a' => 'No. Il trial è gratuito e non richiede nessun dato di pagamento. Durante il trial hai accesso completo al piano Plus. Alla scadenza puoi scegliere se attivare Base o Plus.',
                    ],
                    [
                        'q' => 'Qual è la differenza tra Base e Plus?',
                        'a' => 'Il piano Base include la gestione appuntamenti, le notifiche email, il portale clienti e la sincronizzazione con Google Calendar. Il piano Plus aggiunge l\'Assistente AI WhatsApp: i tuoi clienti possono prenotare e cancellare direttamente via chat.',
                    ],
                ] as $faq)
                <details class="group py-4 first:pt-0 last:pb-0">
                    <summary class="flex items-center justify-between gap-4 cursor-pointer list-none text-sm font-medium text-gray-900 dark:text-white select-none">
                        {{ $faq['q'] }}
                        <x-heroicon-m-chevron-down class="w-4 h-4 text-gray-400 shrink-0 transition-transform duration-200 group-open:rotate-180"/>
                    </summary>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">{{ $faq['a'] }}</p>
                </details>
                @endforeach
            </div>
        </x-filament::section>

        {{-- ═══════════ FEATURES (solo stato expired) ═══════════ --}}

        @if ($status === 'expired')
        <x-filament::section>
            <x-slot name="heading">Cosa include BookingApp</x-slot>
            <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-sm text-gray-700 dark:text-gray-300">
                @foreach ([
                    'Appuntamenti e prenotazioni online illimitati',
                    'Gestione staff, servizi e disponibilità',
                    'Notifiche automatiche ai clienti (email + SMS)',
                    'Lista d\'attesa intelligente',
                    'Pagamenti online integrati',
                    'Nessun contratto — cancella quando vuoi',
                ] as $feature)
                <li class="flex items-center gap-2.5">
                    <x-heroicon-m-check-circle class="w-4 h-4 text-teal-500 shrink-0"/>
                    {{ $feature }}
                </li>
                @endforeach
            </ul>
        </x-filament::section>
        @endif

    </div>
</x-filament-panels::page>
