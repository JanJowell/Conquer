(() => {
    if (window.initializeEventPaymentSelections) {
        window.initializeEventPaymentSelections();
        return;
    }
    const selector = 'input[type="checkbox"][data-payment-provider]';

    const initialize = () => {
        document.querySelectorAll('[data-event-payment-selection]').forEach((section) => {
            if (section.dataset.paymentSelectionBound === 'true') return;
            section.dataset.paymentSelectionBound = 'true';

            // No initial normalization: preserve restored selections and errors.
            // The backend remains authoritative when scripts are absent/bypassed.
            section.addEventListener('change', ({ target }) => {
                if (!(target instanceof HTMLInputElement) || !target.matches(selector) || target.disabled) return;

                const status = section.querySelector('[data-payment-selection-status]');
                if (status) status.textContent = '';
                if (!target.checked) return;

                let replaced = false;
                section.querySelectorAll(selector).forEach((other) => {
                    if (other !== target && other.dataset.paymentProvider === target.dataset.paymentProvider && other.checked) {
                        other.checked = false;
                        replaced = true;
                    }
                });
                if (status && replaced) status.textContent = `${target.dataset.paymentProvider} selection replaced.`;
            });
        });
    };

    window.initializeEventPaymentSelections = initialize;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
    document.addEventListener('livewire:navigated', initialize);
})();
