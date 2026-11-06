function initializeTemplateEditor() {
    const form = document.querySelector('[data-template-editor]');
    if (!form || form.dataset.editorLoading) return;
    form.dataset.editorLoading = 'true';
    import('./template-editor.js').then(({ mount }) => mount(form)).catch(() => {
        form.querySelector('[data-editor-status]').textContent = 'L’editor non si è caricato. Ricarica la pagina per riprovare.';
    });
}

document.addEventListener('DOMContentLoaded', initializeTemplateEditor);
document.addEventListener('livewire:navigated', initializeTemplateEditor);
