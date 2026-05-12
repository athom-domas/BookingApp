const ready = (callback) => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
        return;
    }

    callback();
};

ready(() => {
    const bookingForm = document.querySelector('[data-booking-form]');

    if (bookingForm) {
        const serviceSelect = bookingForm.querySelector('[data-service-select]');
        const staffSelect = bookingForm.querySelector('[data-staff-select]');
        const dateInput = bookingForm.querySelector('[data-date-input]');
        const slotSelect = bookingForm.querySelector('[data-slot-select]');
        const slotStatus = bookingForm.querySelector('[data-slot-status]');

        const setSlotStatus = (message, visible = true) => {
            if (!slotStatus) {
                return;
            }

            slotStatus.textContent = message;
            slotStatus.classList.toggle('hidden', !visible);
        };

        const resetSlots = (message = 'Seleziona prima servizio, staff e data') => {
            slotSelect.replaceChildren(new Option(message, ''));
            setSlotStatus('', false);
        };

        const filterStaff = () => {
            const selectedService = serviceSelect.value;

            Array.from(staffSelect.options).forEach((option) => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const serviceIds = (option.dataset.serviceIds || '').split(',').filter(Boolean);
                const visible = selectedService === '' || serviceIds.includes(selectedService);
                option.hidden = !visible;

                if (!visible && option.selected) {
                    staffSelect.value = '';
                }
            });
        };

        const loadSlots = async () => {
            const serviceId = serviceSelect.value;
            const staffId = staffSelect.value;
            const date = dateInput.value;

            resetSlots();

            if (!serviceId || !staffId || !date) {
                return;
            }

            setSlotStatus('Caricamento slot...');

            try {
                const params = new URLSearchParams({ staff_id: staffId, date });
                const response = await fetch(`/api/services/${serviceId}/slots?${params.toString()}`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('Impossibile caricare gli slot.');
                }

                const payload = await response.json();
                const slots = payload.data || [];

                if (slots.length === 0) {
                    resetSlots('Nessuno slot disponibile');
                    setSlotStatus('Non ci sono orari disponibili per questa combinazione.');
                    return;
                }

                slotSelect.replaceChildren(new Option('Seleziona un orario', ''));

                slots.forEach((slot) => {
                    const start = String(slot.start_time).slice(0, 5);
                    const end = String(slot.end_time).slice(0, 5);
                    slotSelect.add(new Option(`${start} - ${end}`, `${date} ${slot.start_time}`));
                });

                setSlotStatus('', false);
            } catch (error) {
                resetSlots('Errore caricamento slot');
                setSlotStatus(error.message || 'Impossibile caricare gli slot.');
            }
        };

        serviceSelect.addEventListener('change', () => {
            filterStaff();
            loadSlots();
        });
        staffSelect.addEventListener('change', loadSlots);
        dateInput.addEventListener('change', loadSlots);

        filterStaff();
        loadSlots();
    }

    const stripeForm = document.querySelector('[data-stripe-payment]');

    if (stripeForm && window.Stripe) {
        const stripe = window.Stripe(stripeForm.dataset.publicKey);
        const elements = stripe.elements({ clientSecret: stripeForm.dataset.clientSecret });
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
