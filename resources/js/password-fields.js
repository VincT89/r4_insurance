function initPasswordFields() {
    document.querySelectorAll('[data-password-field]:not(.js-bound)').forEach((field) => {
        const input = field.querySelector('input');
        const toggle = field.querySelector('[data-password-toggle]');
        const toggleLabel = field.querySelector('[data-password-toggle-label]');
        const hiddenIcon = field.querySelector('[data-password-icon-hidden]');
        const visibleIcon = field.querySelector('[data-password-icon-visible]');
        const status = field.querySelector('[data-password-status]');

        if (!input || !toggle || !toggleLabel || !hiddenIcon || !visibleIcon || !status) {
            return;
        }

        const showLabel = field.dataset.showLabel || 'Mostra password';
        const hideLabel = field.dataset.hideLabel || 'Nascondi password';

        field.classList.add('js-bound');

        toggle.addEventListener('click', () => {
            const willShow = input.type === 'password';

            input.type = willShow ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', willShow ? 'true' : 'false');
            toggle.setAttribute('aria-label', willShow ? hideLabel : showLabel);
            toggle.setAttribute('title', willShow ? hideLabel : showLabel);
            toggleLabel.textContent = willShow ? 'Nascondi' : 'Mostra';
            hiddenIcon.toggleAttribute('hidden', willShow);
            visibleIcon.toggleAttribute('hidden', !willShow);
            status.textContent = willShow ? 'Password visibile.' : 'Password nascosta.';
        });
    });

    document.querySelectorAll('[data-secret-reveal]:not(.js-bound)').forEach((container) => {
        const value = container.querySelector('[data-secret-value]');
        const toggle = container.querySelector('[data-secret-toggle]');
        const copy = container.querySelector('[data-secret-copy]');
        const status = container.querySelector('[data-secret-status]');
        const secret = container.dataset.secret || '';

        if (!value || !toggle || !copy || !status || !secret) {
            return;
        }

        container.classList.add('js-bound');

        toggle.addEventListener('click', () => {
            const willShow = container.dataset.visible !== 'true';

            container.dataset.visible = willShow ? 'true' : 'false';
            value.textContent = willShow ? secret : '••••••••';
            toggle.setAttribute('aria-pressed', willShow ? 'true' : 'false');
            toggle.setAttribute('aria-label', willShow ? 'Nascondi password temporanea' : 'Mostra password temporanea');
            toggle.textContent = willShow ? 'Nascondi' : 'Mostra';
            status.textContent = willShow ? 'Password temporanea visibile.' : 'Password temporanea nascosta.';
        });

        copy.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(secret);
                status.textContent = 'Password temporanea copiata.';
            } catch (error) {
                status.textContent = 'Impossibile copiare automaticamente la password.';
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initPasswordFields);
document.addEventListener('livewire:navigated', initPasswordFields);
document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(initPasswordFields));
    });
});

export { initPasswordFields };
