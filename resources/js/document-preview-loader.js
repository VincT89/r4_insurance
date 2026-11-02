function initialize() {
    const root = document.querySelector('[data-document-preview]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    import('./document-preview.js').then(({ mount }) => mount(root)).catch(() => {
        root.dataset.state = 'error';
        root.querySelector('[data-preview-status]').textContent = 'Il lettore non è disponibile. Ricarica la pagina oppure usa Scarica PDF.';
    });
}

document.addEventListener('DOMContentLoaded', initialize);
document.addEventListener('livewire:navigated', initialize);
