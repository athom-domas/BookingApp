@extends('layouts.app')

@section('title', 'Pagamento')

@push('head')
    <script src="https://js.stripe.com/v3/"></script>
@endpush

@section('content')
    <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_380px]">
        <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            <h1 class="text-2xl font-semibold text-gray-950">Pagamento prenotazione</h1>
            <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                <div>
                    <dt class="text-sm font-medium text-gray-600">Servizio</dt>
                    <dd class="mt-1 text-base text-gray-950">{{ $appointment->service->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600">Data</dt>
                    <dd class="mt-1 text-base text-gray-950">{{ $appointment->scheduled_date->format('d/m/Y H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600">Staff</dt>
                    <dd class="mt-1 text-base text-gray-950">{{ $appointment->staff->name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-600">Importo</dt>
                    <dd class="mt-1 text-base font-semibold text-gray-950">{{ number_format((float) $payment->amount, 2, ',', '.') }} euro</dd>
                </div>
            </dl>
        </div>

        <aside class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
            @if ($stripePublicKey && $clientSecret)
                <form data-stripe-payment data-public-key="{{ $stripePublicKey }}" data-client-secret="{{ $clientSecret }}" class="space-y-5">
                    <div id="payment-element" class="min-h-32 rounded-md border border-gray-200 p-3"></div>
                    <p class="hidden text-sm text-red-700" data-payment-error></p>
                    <button type="submit" class="w-full rounded-md bg-blue-700 px-4 py-3 text-sm font-semibold text-white shadow-sm hover:bg-blue-800">
                        Paga ora
                    </button>
                </form>

                <form method="POST" action="{{ route('portal.appointments.payment.confirm', $appointment) }}" data-payment-confirm-form class="hidden">
                    @csrf
                </form>
            @else
                <div class="rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-900">
                    Configurazione Stripe incompleta. Verifica chiave pubblica e client secret del PaymentIntent.
                </div>
            @endif
        </aside>
    </section>
@endsection
