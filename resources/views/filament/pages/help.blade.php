<x-filament-panels::page>
    @php $unconfigured = collect($integrationStatuses)->where('configured', false)->values() @endphp
    @if($unconfigured->isNotEmpty())
    <div class="mb-6 rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 px-5 py-4">
        <div class="flex items-center gap-2 mb-3">
            <svg class="h-4 w-4 text-amber-600 dark:text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
            </svg>
            <p class="text-sm font-semibold text-amber-800 dark:text-amber-200">Integrazioni da configurare</p>
        </div>
        <div class="space-y-1.5">
            @foreach($unconfigured as $status)
            <div class="flex items-center justify-between">
                <p class="text-sm text-amber-700 dark:text-amber-300">{{ $status['label'] }} non è ancora configurato</p>
                <a href="{{ $integrationSettingsUrl }}" class="text-xs font-medium text-amber-700 dark:text-amber-400 hover:underline whitespace-nowrap ml-4">Configura ora →</a>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div x-data="{ guide: null }">

        {{-- INDICE GUIDE --}}
        <div x-show="guide === null" x-transition.opacity>
            <div class="grid gap-4 sm:grid-cols-3">

                <button @click="guide = 'setup-salone'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-amber-400 dark:hover:border-amber-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/40 group-hover:bg-amber-200 dark:group-hover:bg-amber-800/60 transition-colors">
                            <svg class="h-5 w-5 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 2.189a3.004 3.004 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Setup iniziale del salone</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come configurare profilo, logo, orari e aspetto del portale clienti per iniziare ad accettare prenotazioni.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-amber-600 dark:text-amber-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'staff-servizi'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-violet-400 dark:hover:border-violet-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/40 group-hover:bg-violet-200 dark:group-hover:bg-violet-800/60 transition-colors">
                            <svg class="h-5 w-5 text-violet-600 dark:text-violet-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Gestione staff e servizi</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come aggiungere collaboratori, creare servizi, assegnarli allo staff e impostare la disponibilità.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-violet-600 dark:text-violet-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'stripe'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-indigo-400 dark:hover:border-indigo-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/40 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-800/60 transition-colors">
                            <svg class="h-5 w-5 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Pagamenti con Stripe</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come collegare il tuo account Stripe per accettare pagamenti dagli appuntamenti e dagli ordini.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-indigo-600 dark:text-indigo-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'whatsapp'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-green-400 dark:hover:border-green-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/40 group-hover:bg-green-200 dark:group-hover:bg-green-800/60 transition-colors">
                            <svg class="h-5 w-5 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">WhatsApp con Meta Cloud API</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come configurare Meta Cloud API per inviare promemoria automatici via WhatsApp ai clienti, gratuitamente.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-green-600 dark:text-green-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

                <button @click="guide = 'google-calendar'"
                    class="group text-left rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 hover:shadow-md transition-all">
                    <div class="flex items-start gap-4">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40 group-hover:bg-blue-200 dark:group-hover:bg-blue-800/60 transition-colors">
                            <svg class="h-5 w-5 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="font-semibold text-gray-900 dark:text-white">Sincronizzazione Google Calendar</p>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Come collegare un Google Calendar per sincronizzare automaticamente gli appuntamenti confermati.</p>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center gap-1 text-xs font-medium text-blue-600 dark:text-blue-400">
                        Leggi la guida
                        <svg class="h-3.5 w-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                        </svg>
                    </div>
                </button>

            </div>
        </div>

        {{-- GUIDA: STRIPE --}}
        <div x-show="guide === 'stripe'" x-transition.opacity style="display:none">
            <div class="mb-5">
                <button @click="guide = null" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    Torna alle guide
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-6 py-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 dark:bg-indigo-900/40">
                        <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Pagamenti con Stripe</h2>
                </div>
                <div class="px-6 py-5 space-y-5 text-sm text-gray-700 dark:text-gray-300">
                    <p>Per accettare pagamenti online dagli appuntamenti e dagli ordini, il salone deve collegare il proprio account Stripe. I pagamenti vengono accreditati <strong>direttamente sul conto bancario del salone</strong>.</p>

                    <ol class="space-y-5 list-none">
                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea un account Stripe</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">stripe.com</span> e registra un account. Inserisci i dati della tua azienda e il conto bancario su cui ricevere i pagamenti.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">2</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Ottieni le chiavi API</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Dal pannello Stripe, vai su <strong>Sviluppatori → Chiavi API</strong>. Copia la <strong>Chiave pubblica</strong> (inizia con <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">pk_live_</span>) e la <strong>Chiave segreta</strong> (inizia con <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">sk_live_</span>).</p>
                                <p class="mt-1.5 text-amber-600 dark:text-amber-400 text-xs">⚠ Non condividere mai la chiave segreta. Non inviarla via email o chat.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">3</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Configura il Webhook</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Il webhook aggiorna automaticamente lo stato dei pagamenti. Dal pannello Stripe vai su <strong>Sviluppatori → Webhook → Aggiungi endpoint</strong>.</p>
                                <div class="mt-2 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 px-4 py-3">
                                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">URL endpoint da inserire:</p>
                                    <p class="font-mono text-xs text-gray-900 dark:text-white break-all">{{ rtrim(request()->getSchemeAndHttpHost(), '/') }}/stripe/webhook</p>
                                </div>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">Seleziona gli eventi: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">payment_intent.succeeded</span> e <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">payment_intent.payment_failed</span>. Dopo aver salvato, copia la <strong>Firma segreta</strong> del webhook.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">4</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Inserisci le chiavi nel pannello</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Dal menu laterale vai su <strong>Impostazioni → Integrazioni → sezione Stripe</strong> e incolla le tre chiavi: chiave pubblica, chiave segreta e firma del webhook. Salva.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">✓</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Tutto pronto</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">I clienti potranno pagare gli appuntamenti e gli ordini online. Gli importi vengono accreditati entro 2–7 giorni lavorativi in base alle impostazioni del tuo account Stripe.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 text-xs text-blue-700 dark:text-blue-300">
                        <strong>Modalità test:</strong> per provare prima di andare live, usa le chiavi <span class="font-mono bg-blue-100 dark:bg-blue-900/40 px-1 rounded">pk_test_</span> / <span class="font-mono bg-blue-100 dark:bg-blue-900/40 px-1 rounded">sk_test_</span>. Numero di carta di test: <span class="font-mono bg-blue-100 dark:bg-blue-900/40 px-1 rounded">4242 4242 4242 4242</span>.
                    </div>
                </div>
            </div>
        </div>

        {{-- GUIDA: WHATSAPP META --}}
        <div x-show="guide === 'whatsapp'" x-transition.opacity style="display:none">
            <div class="mb-5">
                <button @click="guide = null" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    Torna alle guide
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-6 py-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/40">
                        <svg class="h-4 w-4 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 011.037-.443 48.282 48.282 0 005.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">WhatsApp con Meta Cloud API</h2>
                </div>
                <div class="px-6 py-5 space-y-5 text-sm text-gray-700 dark:text-gray-300">
                    <p>Meta Cloud API permette di inviare <strong>promemoria automatici via WhatsApp</strong> ai clienti prima dell'appuntamento. Le prime 1.000 conversazioni al mese sono gratuite.</p>

                    <ol class="space-y-5 list-none">
                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea un'app su Meta for Developers</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">developers.facebook.com</span>, accedi con il tuo account Facebook/Meta e clicca <strong>Crea app</strong>. Scegli il tipo <strong>Business</strong>, dai un nome all'app e clicca Avanti.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">2</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Aggiungi il prodotto WhatsApp all'app</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Nel pannello dell'app, sotto <strong>Aggiungi prodotti</strong>, cerca <strong>WhatsApp</strong> e clicca <strong>Configura</strong>. Segui la procedura per collegare o creare un account WhatsApp Business.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">3</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Ottieni il Phone Number ID</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>WhatsApp → Configurazione API</strong> nel menu dell'app. Nella sezione <strong>Da</strong>, seleziona il numero di telefono e copia il <strong>Phone Number ID</strong> (stringa numerica visibile sotto al numero).</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">4</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea un Access Token permanente</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Per produzione usa un <strong>System User token</strong>: vai su <strong>Meta Business Suite → Impostazioni → Utenti → Utenti di sistema</strong>, crea un utente di sistema di tipo Admin, aggiungi l'app come risorsa con permesso <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">whatsapp_business_messaging</span>, poi clicca <strong>Genera token</strong> e seleziona scadenza <strong>Mai</strong>. Copia il token generato.</p>
                                <p class="mt-1.5 text-amber-600 dark:text-amber-400 text-xs">⚠ Il token viene mostrato una sola volta. Salvalo subito in un posto sicuro.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">5</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea e fai approvare il template di messaggio</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">I messaggi WhatsApp Business devono usare template approvati da Meta. Vai su <strong>WhatsApp → Gestione template messaggi</strong> e crea un nuovo template con queste caratteristiche:</p>
                                <ul class="mt-2 space-y-1 text-gray-500 dark:text-gray-400 list-disc list-inside">
                                    <li>Nome: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">appointment_reminder</span></li>
                                    <li>Categoria: <strong>Utility</strong></li>
                                    <li>Lingua: <strong>Italiano</strong></li>
                                    <li>Corpo: <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">Ciao {{1}}, promemoria per il tuo appuntamento {{2}} il {{3}} alle {{4}} con {{5}}.</span></li>
                                </ul>
                                <p class="mt-2 text-gray-500 dark:text-gray-400">I valori <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">{{1}}</span>…<span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1 rounded">{{5}}</span> vengono sostituiti automaticamente con nome cliente, servizio, data, ora e nome del collaboratore. L'approvazione Meta richiede solitamente poche ore.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">6</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Inserisci le credenziali nel pannello</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Dal menu laterale vai su <strong>Impostazioni → Integrazioni → sezione WhatsApp (Meta Cloud API)</strong> e incolla l'Access Token, il Phone Number ID e il nome del template. Salva.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-700 text-xs font-bold text-white">✓</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Tutto pronto</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">I clienti che scelgono WhatsApp nelle loro impostazioni riceveranno i promemoria automatici via WhatsApp. Se un cliente non ha WhatsApp configurato o l'invio fallisce, riceve comunque il promemoria via email.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-xs text-green-700 dark:text-green-300">
                        <strong>Costi:</strong> Le prime 1.000 conversazioni avviate dal business al mese sono gratuite. Oltre questa soglia il costo è di circa 0,06–0,09 € per conversazione (24 ore). Consulta il listino su <span class="font-mono bg-green-100 dark:bg-green-900/40 px-1 rounded">developers.facebook.com/docs/whatsapp/pricing</span>.
                    </div>
                </div>
            </div>
        </div>

        {{-- GUIDA: GOOGLE CALENDAR --}}
        <div x-show="guide === 'google-calendar'" x-transition.opacity style="display:none">
            <div class="mb-5">
                <button @click="guide = null" class="inline-flex items-center gap-1.5 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/>
                    </svg>
                    Torna alle guide
                </button>
            </div>

            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
                <div class="flex items-center gap-3 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60 px-6 py-4">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/40">
                        <svg class="h-4 w-4 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                        </svg>
                    </div>
                    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Sincronizzazione Google Calendar</h2>
                </div>
                <div class="px-6 py-5 space-y-5 text-sm text-gray-700 dark:text-gray-300">
                    <p>Collegando un Google Calendar, ogni appuntamento <strong>confermato</strong> viene aggiunto automaticamente al calendario. Se l'appuntamento viene <strong>cancellato</strong>, l'evento viene rimosso. Il servizio usa un <strong>Service Account</strong> Google, che non richiede login e funziona in background.</p>

                    <ol class="space-y-5 list-none">
                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">1</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea un progetto su Google Cloud</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">console.cloud.google.com</span> e accedi con il tuo account Google. Clicca su <strong>Seleziona progetto → Nuovo progetto</strong>, dai un nome (es. "Gestionale Salone") e clicca <strong>Crea</strong>.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">2</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Abilita l'API Google Calendar</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Nel pannello del progetto vai su <strong>API e servizi → Libreria</strong>. Cerca <strong>Google Calendar API</strong>, aprila e clicca <strong>Abilita</strong>.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">3</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Crea un Service Account</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Vai su <strong>API e servizi → Credenziali → Crea credenziali → Account di servizio</strong>. Inserisci un nome (es. "calendar-sync") e clicca <strong>Crea e continua</strong>. Non è necessario assegnare ruoli al progetto — clicca <strong>Fine</strong>.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">4</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Scarica il file JSON delle credenziali</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Nell'elenco degli account di servizio, clicca sull'account appena creato. Vai su <strong>Chiavi → Aggiungi chiave → Crea nuova chiave → JSON</strong>. Verrà scaricato un file <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">.json</span> — tienilo al sicuro.</p>
                                <p class="mt-1.5 text-gray-500 dark:text-gray-400">Prendi nota dell'indirizzo email del Service Account (visibile nel campo <strong>Email</strong>), ha la forma <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">nome@progetto.iam.gserviceaccount.com</span>. Ti servirà al passo 6.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">5</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Ottieni l'ID del calendario</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Apri <strong>Google Calendar</strong> sul browser. Clicca sui tre puntini accanto al calendario che vuoi usare (puoi crearne uno nuovo appositamente) → <strong>Impostazioni e condivisione</strong>. In fondo alla pagina, nella sezione <strong>Integra calendario</strong>, copia l'<strong>ID calendario</strong>. Il calendario principale ha come ID il tuo indirizzo email; i calendari secondari hanno un ID lungo del tipo <span class="font-mono text-xs bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded">abc123xyz@group.calendar.google.com</span>.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">6</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Condividi il calendario con il Service Account</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Sempre nelle impostazioni del calendario, vai su <strong>Condividi con persone e gruppi</strong>. Aggiungi l'email del Service Account (dal passo 4) con il ruolo <strong>Modifica eventi</strong>. Salva.</p>
                                <p class="mt-1.5 text-amber-600 dark:text-amber-400 text-xs">⚠ Senza questo passaggio il Service Account non ha i permessi per creare eventi e la sincronizzazione non funzionerà.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white">7</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Inserisci le credenziali nel pannello</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Dal menu laterale vai su <strong>Impostazioni → Integrazioni → sezione Google Calendar</strong>. Incolla l'<strong>ID calendario</strong> nel primo campo. Nel secondo campo, apri il file JSON scaricato con un editor di testo e incolla l'intero contenuto. Salva.</p>
                            </div>
                        </li>

                        <li class="flex gap-4">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-green-600 text-xs font-bold text-white">✓</span>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white">Tutto pronto</p>
                                <p class="mt-0.5 text-gray-500 dark:text-gray-400">Da questo momento ogni appuntamento confermato apparirà automaticamente nel calendario con il nome del servizio e del cliente. Le cancellazioni rimuovono l'evento dal calendario.</p>
                            </div>
                        </li>
                    </ol>

                    <div class="rounded-lg bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-4 py-3 text-xs text-blue-700 dark:text-blue-300">
                        <strong>Gratuito:</strong> Google Calendar API ha un limite di 1.000.000 richieste al giorno con il piano gratuito, ampiamente sufficiente per qualsiasi salone.
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
