@extends('layouts.app')

@section('title', 'Pagamento')

@push('head')
    <script src="https://js.stripe.com/v3/"></script>
@endpush

@section('content')
    <div class="mx-auto max-w-2xl space-y-4">

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            <h1 class="text-lg font-semibold text-gray-950 dark:text-gray-50">Riepilogo prenotazione</h1>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Servizi</dt>
                    <dd class="font-medium text-gray-950 dark:text-gray-50 text-right">{{ $appointment->services_label }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Data</dt>
                    <dd class="font-medium text-gray-950 dark:text-gray-50">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-gray-500 dark:text-gray-400">Operatore</dt>
                    <dd class="font-medium text-gray-950 dark:text-gray-50">{{ $appointment->staff->name }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-gray-100 dark:border-gray-800 pt-3 mt-1">
                    <dt class="font-semibold text-gray-950 dark:text-gray-50">Totale</dt>
                    <dd class="flex items-baseline gap-2">
                        @if($discountApplied)
                            <span class="line-through text-gray-400">{{ number_format($originalAmount, 2, ',', '.') }} €</span>
                            <span class="text-base font-semibold text-green-600 dark:text-green-400">{{ number_format($discountedAmount, 2, ',', '.') }} €</span>
                            <span class="rounded-full bg-green-100 dark:bg-green-900/40 px-2 py-0.5 text-xs font-semibold text-green-700 dark:text-green-300">−{{ $loyaltyPercentage }}%</span>
                        @else
                            <span class="text-base font-semibold text-gray-950 dark:text-gray-50">{{ number_format($originalAmount, 2, ',', '.') }} €</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </div>

        @if($loyaltyEnabled && $loyaltyEligible)
            <div class="rounded-lg border {{ $discountApplied ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/40' : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900' }} p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold {{ $discountApplied ? 'text-green-800 dark:text-green-300' : 'text-gray-950 dark:text-gray-50' }}">
                            Programma fedeltà
                        </p>
                        @if($discountApplied)
                            <p class="mt-0.5 text-sm text-green-700 dark:text-green-400">
                                Sconto {{ $loyaltyPercentage }}% applicato — {{ $loyaltyThreshold }} punti verranno scalati al completamento.
                            </p>
                        @else
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                Hai {{ $loyaltyPoints }} punti. Applica uno sconto del {{ $loyaltyPercentage }}% scalando {{ $loyaltyThreshold }} punti.
                            </p>
                        @endif
                    </div>
                    @if($discountApplied)
                        <form method="POST" action="{{ route('portal.appointments.payment.discount.remove', $appointment) }}" class="shrink-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 underline underline-offset-2">
                                Rimuovi
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('portal.appointments.payment.discount', $appointment) }}" class="shrink-0">
                            @csrf
                            <button type="submit" class="rounded-md bg-green-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-green-700">
                                Applica sconto
                            </button>
                        </form>
                    @endif
                </div>
                @error('discount')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-6 shadow-sm">
            @if ($stripePublicKey && $clientSecret)
                <p class="mb-4 text-sm font-semibold text-gray-950 dark:text-gray-50">Dati di pagamento</p>
                <form data-stripe-payment data-public-key="{{ $stripePublicKey }}" data-client-secret="{{ $clientSecret }}" data-stripe-account="{{ $stripeAccountId ?? '' }}" class="space-y-4">
                    <div id="payment-element"></div>
                    <p class="hidden text-sm text-red-700 dark:text-red-400" data-payment-error></p>
                    <button type="submit"
                        data-label="Paga {{ number_format($discountedAmount, 2, ',', '.') }} €"
                        class="w-full rounded-lg bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800 disabled:opacity-60">
                        Paga {{ number_format($discountedAmount, 2, ',', '.') }} €
                    </button>
                </form>

                <form method="POST" action="{{ route('portal.appointments.payment.confirm', $appointment) }}" data-payment-confirm-form class="hidden">
                    @csrf
                </form>
            @else
                <div class="rounded-md border border-yellow-200 dark:border-yellow-800 bg-yellow-50 dark:bg-yellow-950 p-4 text-sm text-yellow-900 dark:text-yellow-300">
                    Configurazione Stripe incompleta. Verifica chiave pubblica e client secret del PaymentIntent.
                </div>
            @endif
        </div>

        <div class="pt-2 text-center space-y-3">
            @if($loyaltyEnabled && $pointsToEarn > 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Completando il pagamento guadagnerai {{ $pointsToEarn }} {{ $pointsToEarn === 1 ? 'punto' : 'punti' }} fedeltà.
                </p>
            @endif
            <form method="POST" action="{{ route('portal.appointments.cancel', $appointment) }}"
                  onsubmit="return confirm('Sei sicuro di voler annullare questa prenotazione?')">
                @csrf
                <button type="submit" class="text-sm text-gray-400 hover:text-gray-600 dark:text-gray-500 dark:hover:text-gray-300 underline underline-offset-2">
                    Annulla prenotazione
                </button>
            </form>
        </div>

    </div>
@endsection
