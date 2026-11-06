function initialize() {
    const root = document.querySelector('[data-tablet-signing]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    import('./tablet-signing.js').then(module => module.mount(root)).catch(() => {
        const error = root.querySelector('[data-signing-error]');
        error.hidden = false;
        error.textContent = 'La firma non è disponibile. Ricarica la pagina oppure torna alla polizza e carica una copia firmata.';
    });
}
document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
