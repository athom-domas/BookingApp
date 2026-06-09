@extends('layouts.app')

@section('title', 'Pagamento')

@section('content')
<section class="space-y-8 max-w-lg mx-auto">
    <div>
        <h1 class="font-display text-3xl font-semibold text-gray-950 dark:text-gray-50">Pagamento</h1>
        <p class="mt-1.5 text-sm text-gray-500 dark:text-gray-400">
            Totale ordine: <strong>{{ number_format($order->total, 2, ',', '.') }} €</strong>
        </p>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800 p-6">
        <div id="payment-element" class="mb-6"></div>
        <div id="payment-message" class="hidden mb-4 text-sm text-red-600 dark:text-red-400"></div>
        <button id="submit-btn"
                class="btn-primary w-full rounded-md px-6 py-2.5 text-sm font-semibold text-white disabled:opacity-50">
            Paga {{ number_format($order->total, 2, ',', '.') }} €
        </button>
    </div>
</section>

<script src="https://js.stripe.com/v3/"></script>
<script>
    const stripe = Stripe('{{ $stripePublicKey }}');
    const elements = stripe.elements({ clientSecret: '{{ $clientSecret }}' });
    const paymentElement = elements.create('payment');
    paymentElement.mount('#payment-element');

    document.getElementById('submit-btn').addEventListener('click', async function () {
        this.disabled = true;
        document.getElementById('payment-message').classList.add('hidden');

        const { error } = await stripe.confirmPayment({
            elements,
            confirmParams: {
                return_url: '{{ route('portal.products.stripe-confirm', $order->id) }}',
            },
        });

        if (error) {
            document.getElementById('payment-message').textContent = error.message;
            document.getElementById('payment-message').classList.remove('hidden');
            this.disabled = false;
        }
    });
</script>
@endsection
