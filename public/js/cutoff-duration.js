(() => {
    const controls = [...document.querySelectorAll('[data-cutoff-duration]')];
    const validateTotal = (control) => {
        const hours = control.querySelector('[data-duration-hours]');
        const minutes = control.querySelector('[data-duration-minutes]');
        minutes.setCustomValidity('');
        if (!hours.disabled && hours.value !== '' && minutes.value !== ''
            && hours.validity.valid && minutes.validity.valid
            && Number(hours.value) === 0 && Number(minutes.value) === 0) {
            minutes.setCustomValidity('Enter a duration greater than zero.');
        }
    };
    const refresh = (control) => {
        const preset = control.querySelector('[data-duration-preset]');
        const custom = preset.value === '__custom_duration__';
        control.querySelector('[data-duration-custom]').hidden = !custom;
        control.querySelectorAll('[data-duration-hours], [data-duration-minutes]').forEach((field) => {
            field.disabled = preset.disabled || !custom;
            field.required = !field.disabled;
        });
        validateTotal(control);
    };
    controls.forEach((control) => {
        control.querySelector('[data-duration-preset]').addEventListener('change', () => refresh(control));
        control.querySelectorAll('[data-duration-hours], [data-duration-minutes]').forEach((field) => {
            field.addEventListener('input', () => validateTotal(control));
        });
        refresh(control);
    });
    // Run after the existing form handler enables/disables the event-type panels.
    document.querySelector('#interest_type')?.addEventListener('change', () => {
        queueMicrotask(() => controls.forEach(refresh));
    });
})();
