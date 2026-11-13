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
            const checkpoint = form.elements.namedItem('checkpoint_id');
            if (!event || !category || !checkpoint) return;
            form.dataset.historyBound = 'true';
            const filterCategories = () => {
                category.querySelectorAll('[data-history-event]').forEach((group) => {
                    const excluded = event.value !== '' && group.dataset.historyEvent !== event.value;
                    group.hidden = excluded;
                    group.disabled = excluded;
                });
            };
            const filterCheckpoints = () => {
                let hasCheckpoints = false;
                checkpoint.querySelectorAll('[data-history-category]').forEach((group) => {
                    const excluded = category.value === '' || group.dataset.historyCategory !== category.value;
                    group.hidden = excluded;
                    group.disabled = excluded;
                    if (!excluded) hasCheckpoints = true;
                });
                checkpoint.disabled = !hasCheckpoints;
                if (!hasCheckpoints) checkpoint.value = '';
                checkpoint.options[0].textContent = category.value === '' ? 'Select a category first'
                    : (hasCheckpoints ? 'All checkpoints' : 'No recorded checkpoints');
            };
            event.addEventListener('change', () => {
                category.value = '';
                checkpoint.value = '';
                filterCategories();
                filterCheckpoints();
            });
            category.addEventListener('change', () => {
                checkpoint.value = '';
                filterCheckpoints();
            });
            filterCategories();
            filterCheckpoints();
        });
    };
    window.initializeCheckpointHistory = initialize;
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize, { once: true });
    else initialize();
    document.addEventListener('livewire:navigated', initialize);
})();
