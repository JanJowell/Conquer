(() => {
    if (window.initializeCheckpointHistory) {
        window.initializeCheckpointHistory();
        return;
    }
    const initialize = () => {
        document.querySelectorAll('[data-checkpoint-history-filters]').forEach((form) => {
            if (form.dataset.historyBound === 'true') return;
            const event = form.elements.namedItem('event_id');
            const category = form.elements.namedItem('category_id');
            if (!event || !category) return;
            form.dataset.historyBound = 'true';
            const filterCategories = () => {
                category.querySelectorAll('[data-history-event]').forEach((group) => {
                    const excluded = event.value !== '' && group.dataset.historyEvent !== event.value;
                    group.hidden = excluded;
                    group.disabled = excluded;
                });
            };
            event.addEventListener('change', () => {
                category.value = '';
                filterCategories();
            });
            filterCategories();
        });
    };
    window.initializeCheckpointHistory = initialize;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
    document.addEventListener('livewire:navigated', initialize);
})();
