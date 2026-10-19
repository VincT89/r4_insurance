document.addEventListener('DOMContentLoaded', () => {
    initUIHelpers();
});

document.addEventListener('livewire:navigated', () => {
    initUIHelpers();
});

function initUIHelpers() {
    // Evita listener multipli
    if (window._uiHelpersInitialized) return;
    window._uiHelpersInitialized = true;

    document.addEventListener('click', function(e) {
        // Gestione .js-stop-propagation manuale, se si clicca su un elemento con questa classe
        if (e.target.closest('.js-stop-propagation')) {
            e.stopPropagation();
            // Non blocchiamo il default per permettere ai link/button interni di funzionare
            return;
        }

        const clickableRow = e.target.closest('.js-clickable-row');
        if (clickableRow) {
            // Verifica se il click proviene da elementi interattivi che dovrebbero ignorare la navigazione
            const ignoredElements = ['A', 'BUTTON', 'INPUT', 'SELECT', 'TEXTAREA', 'FORM'];
            if (ignoredElements.includes(e.target.tagName)) {
                return;
            }
            
            // Verifica se il click è in un figlio interattivo o .js-stop-propagation
            const interactiveParent = e.target.closest('a, button, input, select, textarea, form, .js-stop-propagation');
            if (interactiveParent && clickableRow.contains(interactiveParent)) {
                return;
            }

            const href = clickableRow.dataset.href;
            if (href) {
                if (window.Livewire && window.Livewire.navigate) {
                    window.Livewire.navigate(href);
                } else {
                    window.location.href = href;
                }
            }
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('js-auto-submit')) {
            if (e.target.form) {
                e.target.form.submit();
            }
        }
    });
}
