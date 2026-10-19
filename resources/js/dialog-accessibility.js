const dialogState = new WeakMap();

function isOverlayVisible(overlay) {
    if (overlay.classList.contains('overlay')) {
        return overlay.classList.contains('open');
    }

    return !overlay.hasAttribute('x-cloak') && overlay.style.display !== 'none';
}

function focusableElements(dialog) {
    return [...dialog.querySelectorAll(
        'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
    )].filter((element) => element.getClientRects().length > 0);
}

function updateDialogState(overlay) {
    const dialog = overlay.querySelector('[role="dialog"]');
    if (!dialog) {
        return;
    }

    const state = dialogState.get(overlay) || { open: false, opener: null };
    const visible = isOverlayVisible(overlay);

    if (visible && !state.open) {
        state.open = true;
        state.opener = document.activeElement;
        overlay.setAttribute('aria-hidden', 'false');

        requestAnimationFrame(() => {
            const initialFocus = dialog.querySelector('[data-dialog-initial-focus]') || focusableElements(dialog)[0] || dialog;
            initialFocus.focus({ preventScroll: true });
        });
    } else if (!visible && state.open) {
        state.open = false;
        overlay.setAttribute('aria-hidden', 'true');

        if (state.opener instanceof HTMLElement && state.opener.isConnected) {
            state.opener.focus({ preventScroll: true });
        }
    } else if (!visible) {
        overlay.setAttribute('aria-hidden', 'true');
    }

    dialogState.set(overlay, state);
}

function initDialogs() {
    document.querySelectorAll('[data-dialog-overlay]:not(.js-dialog-bound)').forEach((overlay) => {
        const dialog = overlay.querySelector('[role="dialog"]');
        if (!dialog) {
            return;
        }

        overlay.classList.add('js-dialog-bound');

        dialog.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab') {
                return;
            }

            const focusable = focusableElements(dialog);
            if (focusable.length === 0) {
                event.preventDefault();
                dialog.focus();
                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        const observer = new MutationObserver(() => updateDialogState(overlay));
        observer.observe(overlay, {
            attributes: true,
            attributeFilter: ['class', 'style'],
        });

        updateDialogState(overlay);
    });
}

document.addEventListener('DOMContentLoaded', initDialogs);
document.addEventListener('livewire:navigated', initDialogs);
document.addEventListener('livewire:initialized', () => {
    Livewire.hook('commit', ({ succeed }) => {
        succeed(() => queueMicrotask(initDialogs));
    });
});

export { initDialogs };
