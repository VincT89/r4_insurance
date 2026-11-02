function initQuoteRecipients() {
    document.querySelectorAll('[data-quote-recipients]').forEach(root => {
        if (root.dataset.bound) return;
        root.dataset.bound = 'true';
        const boxes = [...root.querySelectorAll('input[name="recipient_keys[]"]')];
        const count = root.querySelector('[data-recipient-count]');
        const search = root.querySelector('[data-recipient-search]');
        const companies = [...root.querySelectorAll('[data-recipient-company]')];
        const group = root.querySelector('[data-recipient-group]');
        const apply = root.querySelector('[data-apply-recipient-group]');
        const result = root.querySelector('[data-recipient-group-result]');
        const refreshCount = () => {
            const selected = boxes.filter(box => box.checked && !box.disabled).length;
            count.textContent = selected === 1 ? '1 destinatario selezionato.' : `${selected} destinatari selezionati.`;
        };
        boxes.forEach(box => box.addEventListener('change', refreshCount));
        search.addEventListener('input', () => {
            const value = search.value.trim().toLocaleLowerCase('it');
            companies.forEach(company => { company.hidden = !company.dataset.search.toLocaleLowerCase('it').includes(value); });
            root.querySelector('[data-recipient-empty]').hidden = companies.some(company => !company.hidden);
        });
        if (apply) {
            apply.disabled = !group.value;
            group.addEventListener('change', () => { apply.disabled = !group.value; });
            apply.addEventListener('click', () => {
                const keys = JSON.parse(group.selectedOptions[0]?.dataset.recipients || '[]');
                if (!group.value) return;
                boxes.forEach(box => { box.checked = !box.disabled && keys.includes(box.value); });
                const available = boxes.filter(box => box.checked).map(box => box.value);
                const missing = keys.filter(key => !available.includes(key)).length;
                result.textContent = missing ? `Gruppo caricato. ${missing} destinatari non disponibili: controlla la selezione.` : 'Gruppo caricato. Puoi modificare liberamente i destinatari.';
                search.value = ''; search.dispatchEvent(new Event('input'));
                refreshCount();
            });
        }
        refreshCount();
    });
}
document.addEventListener('DOMContentLoaded', initQuoteRecipients);
document.addEventListener('livewire:navigated', initQuoteRecipients);
