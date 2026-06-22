import Alpine from 'alpinejs';
import { bookingWizard } from './booking-wizard.js';

const STRIPE_ERRORS = {
    card_declined:             'La carta è stata rifiutata. Verifica i dati o usa un altro metodo di pagamento.',
    insufficient_funds:        'Fondi insufficienti sulla carta.',
    lost_card:                 'La carta risulta bloccata. Contatta la tua banca.',
    stolen_card:               'La carta risulta bloccata. Contatta la tua banca.',
    expired_card:              'La carta è scaduta. Usa una carta valida.',
    incorrect_cvc:             'Il codice di sicurezza (CVV) non è corretto.',
    incorrect_number:          'Il numero della carta non è valido.',
    incorrect_zip:             'Il CAP inserito non corrisponde a quello della carta.',
    invalid_cvc:               'Il codice di sicurezza (CVV) non è valido.',
    invalid_expiry_month:      'Il mese di scadenza non è valido.',
    invalid_expiry_year:       'L\'anno di scadenza non è valido.',
    invalid_number:            'Il numero della carta non è valido.',
    do_not_honor:              'La carta è stata rifiutata dalla banca. Contatta il tuo istituto.',
    do_not_try_again:          'La carta è stata rifiutata. Non riprovare con questa carta.',
    fraudulent:                'Il pagamento è stato rifiutato per motivi di sicurezza.',
    generic_decline:           'La carta è stata rifiutata. Verifica i dati o contatta la tua banca.',
    payment_intent_authentication_failure: 'Autenticazione non riuscita. Riprova.',
    processing_error:          'Si è verificato un errore durante l\'elaborazione. Riprova tra qualche istante.',
};

function stripeErrorMessage(error) {
    if (error.code && STRIPE_ERRORS[error.code]) {
        return STRIPE_ERRORS[error.code];
    }
    if (error.decline_code && STRIPE_ERRORS[error.decline_code]) {
        return STRIPE_ERRORS[error.decline_code];
    }
    return 'Pagamento non completato. Verifica i dati della carta e riprova.';
}

window.bookingWizard = bookingWizard;

window.scrollToSection = function (id) {
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth' });
};

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
                errorTarget.textContent = stripeErrorMessage(error);
                errorTarget.classList.remove('hidden');
                submitButton.disabled = false;
                submitButton.textContent = 'Paga ora';
                return;
            }

            confirmForm.submit();
        });
    }
});
