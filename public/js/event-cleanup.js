(() => {
    if (window.initializeEventCleanup) {
        window.initializeEventCleanup();
        return;
    }

    window.initializeEventCleanup = () => {
        document.querySelectorAll('[data-event-cleanup-open]').forEach(button => {
            if (button.dataset.cleanupInitialized) return;
            button.dataset.cleanupInitialized = 'true';
            button.addEventListener('click', () => {
                const dialog = document.getElementById(button.dataset.eventCleanupOpen);
                if (!dialog || typeof dialog.showModal !== 'function' || dialog.open || dialog.dataset.cleanupPending === 'true') return;
                dialog.returnFocus = button;
                dialog.querySelector('form')?.reset();
                dialog.showModal();
            });
        });

        document.querySelectorAll('[data-event-cleanup-dialog]').forEach(dialog => {
            if (dialog.dataset.cleanupInitialized) return;
            dialog.dataset.cleanupInitialized = 'true';
            const submit = dialog.querySelector('[data-event-cleanup-submit]');
            const reset = () => {
                if (submit) submit.disabled = false;
                dialog.removeAttribute('closedby');
            };
            dialog.querySelectorAll('[data-event-cleanup-close]').forEach(button => {
                button.addEventListener('click', () => {
                    if (dialog.dataset.cleanupPending !== 'true') dialog.close();
                });
            });
            dialog.addEventListener('cancel', event => {
                if (dialog.dataset.cleanupPending === 'true') event.preventDefault();
            });
            dialog.addEventListener('close', () => {
                if (dialog.dataset.cleanupPending !== 'true') reset();
                dialog.returnFocus?.focus();
            });
            dialog.querySelector('[data-event-cleanup-form]')?.addEventListener('submit', event => {
                if (dialog.dataset.cleanupPending === 'true' || submit?.disabled) {
                    event.preventDefault();
                    return;
                }
                dialog.dataset.cleanupPending = 'true';
                dialog.setAttribute('closedby', 'none');
                if (submit) submit.disabled = true;
            });
        });
    };

    document.addEventListener('livewire:navigating', () => {
        document.querySelectorAll('[data-event-cleanup-dialog]').forEach(dialog => {
            if (dialog.open) dialog.close();
        });
    });
    document.addEventListener('livewire:navigated', window.initializeEventCleanup);
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('[data-event-cleanup-submit]').forEach(button => { button.disabled = false; });
        document.querySelectorAll('[data-event-cleanup-dialog]').forEach(dialog => {
            dialog.dataset.cleanupPending = 'false';
            dialog.removeAttribute('closedby');
        });
        window.initializeEventCleanup();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initializeEventCleanup);
    } else {
        window.initializeEventCleanup();
    }
})();
