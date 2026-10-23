document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.passwordToggle);

        if (!input) {
            return;
        }

        const isVisible = input.type === 'text';
        input.type = isVisible ? 'password' : 'text';
        toggle.setAttribute('aria-pressed', String(!isVisible));
        toggle.setAttribute('aria-label', isVisible ? 'Show password' : 'Hide password');
    });
});

document.querySelectorAll('form.auth-form').forEach((form) => {
    form.addEventListener('submit', () => {
        const submitButton = form.querySelector('button[type="submit"]');

        if (submitButton) {
            submitButton.disabled = true;
            submitButton.dataset.originalLabel = submitButton.textContent;
            submitButton.textContent = 'Please wait…';
        }
    });
});
