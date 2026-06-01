import Alpine from 'alpinejs';
import { bookingWizard } from './booking-wizard.js';

window.bookingWizard = bookingWizard;

const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }

    callback();
};

ready(() => {
    Alpine.start();

    const stripeForm = document.querySelector('[data-stripe-payment]');

    if (stripeForm && window.Stripe) {
        const stripe = window.Stripe(stripeForm.dataset.publicKey);
        const isDark = document.documentElement.classList.contains('dark');
        const elements = stripe.elements({
            clientSecret: stripeForm.dataset.clientSecret,
            appearance: { theme: isDark ? 'night' : 'stripe' },
        });
        const paymentElement = elements.create('payment');
        const errorTarget = stripeForm.querySelector('[data-payment-error]');
        const submitButton = stripeForm.querySelector('button[type="submit"]');
        const confirmForm = document.querySelector('[data-payment-confirm-form]');

        paymentElement.mount('#payment-element');

        stripeForm.addEventListener('submit', async (event) => {
            event.preventDefault();

            submitButton.disabled = true;
            submitButton.textContent = 'Pagamento in corso...';
            errorTarget.classList.add('hidden');

            const { error } = await stripe.confirmPayment({
                elements,
                redirect: 'if_required',
            });

            if (error) {
                errorTarget.textContent = error.message || 'Pagamento non completato.';
                errorTarget.classList.remove('hidden');
                submitButton.disabled = false;
                submitButton.textContent = 'Paga ora';
                return;
            }

            confirmForm.submit();
        });
    }
});
