@extends('layouts.marketing')

@section('title', 'Contatti')
@section('description', 'Contattaci per una demo gratuita o per qualsiasi domanda su GestionalePro.')

@section('content')
<section class="min-h-[calc(100vh-64px)] bg-cream py-20 px-6">
    <div class="max-w-5xl mx-auto">

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-12 lg:gap-16 items-start">

            {{-- LEFT: info --}}
            <div class="lg:col-span-2">
                <p class="text-xs font-semibold text-terra uppercase tracking-widest mb-3">Contatti</p>
                <h1 class="font-display text-3xl sm:text-4xl font-semibold text-ink mb-4 leading-tight">
                    Parliamo del tuo salone
                </h1>
                <p class="text-ink-muted leading-relaxed mb-10">
                    Hai domande su GestionalePro o vuoi vedere una demo dal vivo?
                    Compila il form e ti risponderemo entro 24 ore nei giorni lavorativi.
                </p>

                <div class="space-y-5">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-terra-light flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-terra" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-ink text-sm">Risposta rapida</p>
                            <p class="text-ink-muted text-sm">Entro 24 ore nei giorni lavorativi</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-terra-light flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-terra" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-ink text-sm">Supporto in italiano</p>
                            <p class="text-ink-muted text-sm">7 giorni su 7</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-terra-light flex items-center justify-center shrink-0 mt-0.5">
                            <svg class="w-5 h-5 text-terra" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                        </div>
                        <div>
                            <p class="font-semibold text-ink text-sm">Demo dal vivo</p>
                            <p class="text-ink-muted text-sm">Ti mostriamo tutto in una videochiamata</p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: form --}}
            <div class="lg:col-span-3">
                <div class="bg-cream rounded-2xl border border-warm-border shadow-sm p-8">

                    @if(session('success'))
                    <div class="mb-6 flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl px-5 py-4">
                        <svg class="w-5 h-5 text-green-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <p class="text-sm text-green-700 font-medium">{{ session('success') }}</p>
                    </div>
                    @endif

                    <form method="POST" action="{{ route('contact.store') }}" class="space-y-5">
                        @csrf

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="name" class="block text-sm font-medium text-ink-muted mb-1.5">
                                    Nome e cognome <span class="text-red-400">*</span>
                                </label>
                                <input type="text" id="name" name="name"
                                       value="{{ old('name') }}"
                                       placeholder="Mario Rossi"
                                       class="w-full px-4 py-2.5 rounded-xl border text-sm transition
                                              {{ $errors->has('name') ? 'border-red-300 bg-red-50 focus:ring-red-300' : 'border-warm-border focus:border-terra focus:ring-terra/20' }}
                                              focus:outline-none focus:ring-2">
                                @error('name')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="email" class="block text-sm font-medium text-ink-muted mb-1.5">
                                    Email <span class="text-red-400">*</span>
                                </label>
                                <input type="email" id="email" name="email"
                                       value="{{ old('email') }}"
                                       placeholder="mario@salone.it"
                                       class="w-full px-4 py-2.5 rounded-xl border text-sm transition
                                              {{ $errors->has('email') ? 'border-red-300 bg-red-50 focus:ring-red-300' : 'border-warm-border focus:border-terra focus:ring-terra/20' }}
                                              focus:outline-none focus:ring-2">
                                @error('email')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                            <div>
                                <label for="phone" class="block text-sm font-medium text-ink-muted mb-1.5">
                                    Telefono <span class="text-gray-400 font-normal">(opzionale)</span>
                                </label>
                                <input type="tel" id="phone" name="phone"
                                       value="{{ old('phone') }}"
                                       placeholder="+39 340 000 0000"
                                       class="w-full px-4 py-2.5 rounded-xl border border-warm-border text-sm transition
                                              focus:outline-none focus:border-terra focus:ring-2 focus:ring-terra/20">
                            </div>

                            <div>
                                <label for="subject" class="block text-sm font-medium text-ink-muted mb-1.5">
                                    Tipo di richiesta <span class="text-red-400">*</span>
                                </label>
                                <select id="subject" name="subject"
                                        class="w-full px-4 py-2.5 rounded-xl border text-sm transition bg-white
                                               {{ $errors->has('subject') ? 'border-red-300 bg-red-50 focus:ring-red-300' : 'border-warm-border focus:border-terra focus:ring-terra/20' }}
                                               focus:outline-none focus:ring-2">
                                    <option value="" disabled {{ old('subject') ? '' : 'selected' }}>Seleziona…</option>
                                    <option value="demo"    {{ old('subject') === 'demo'    ? 'selected' : '' }}>Richiesta demo gratuita</option>
                                    <option value="info"    {{ old('subject') === 'info'    ? 'selected' : '' }}>Informazioni generali</option>
                                    <option value="support" {{ old('subject') === 'support' ? 'selected' : '' }}>Supporto tecnico</option>
                                    <option value="other"   {{ old('subject') === 'other'   ? 'selected' : '' }}>Altro</option>
                                </select>
                                @error('subject')
                                <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="message" class="block text-sm font-medium text-ink-muted mb-1.5">
                                Messaggio <span class="text-red-400">*</span>
                            </label>
                            <textarea id="message" name="message" rows="5"
                                      placeholder="Dimmi pure come posso aiutarti…"
                                      class="w-full px-4 py-2.5 rounded-xl border text-sm transition resize-none
                                             {{ $errors->has('message') ? 'border-red-300 bg-red-50 focus:ring-red-300' : 'border-warm-border focus:border-terra focus:ring-terra/20' }}
                                             focus:outline-none focus:ring-2">{{ old('message') }}</textarea>
                            @error('message')
                            <p class="mt-1 text-xs text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <button type="submit"
                                class="w-full bg-terra hover:bg-terra/90 text-white font-semibold py-3.5 rounded-xl transition text-sm shadow-sm shadow-terra/20">
                            Invia messaggio
                        </button>

                        <p class="text-xs text-ink-muted/70 text-center">
                            Inviando il messaggio accetti la nostra
                            <a href="{{ route('legal.privacy') }}" class="text-terra hover:underline">Privacy Policy</a>.
                        </p>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>
@endsection
