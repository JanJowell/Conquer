<script>
    document.querySelectorAll('[data-announcement-image-field]').forEach((field) => {
        const input = field.querySelector('[data-announcement-image-input]');
        const preview = field.querySelector('[data-announcement-image-preview]');
        const previewContainer = field.querySelector('[data-announcement-image-preview-container]');
        const remove = field.querySelector('[data-announcement-remove-image]');
        let objectUrl = null;

        const resetPreview = () => {
            const currentSrc = preview.dataset.currentSrc || '';
            preview.src = currentSrc;
            previewContainer.classList.toggle('hidden', currentSrc === '' || remove?.checked === true);
        };

        input.addEventListener('change', () => {
            if (objectUrl) {
                URL.revokeObjectURL(objectUrl);
                objectUrl = null;
            }

            const file = input.files?.[0];
            if (!file) {
                resetPreview();
                return;
            }

            if (remove) {
                remove.checked = false;
            }

            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
            previewContainer.classList.remove('hidden');
        });

        remove?.addEventListener('change', () => {
            if (remove.checked) {
                input.value = '';
            }
            resetPreview();
        });

        resetPreview();
    });
</script>
