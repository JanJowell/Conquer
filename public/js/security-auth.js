document.querySelectorAll('[data-security-form]').forEach((form) => {
    const button = form.querySelector('[data-submit-label]');
    if (!button) return;
    const reset = () => {
        button.disabled = false;
        button.textContent = button.dataset.submitLabel;
        form.removeAttribute('aria-busy');
    };
    form.addEventListener('submit', () => {
        button.disabled = true;
        button.textContent = 'Verifying…';
        form.setAttribute('aria-busy', 'true');
    });
    window.addEventListener('pageshow', reset);
});
document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    button.hidden = false;
    button.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        button.textContent = reveal ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', String(reveal));
        button.setAttribute('aria-label', reveal ? 'Hide password' : 'Show password');
    });
});
