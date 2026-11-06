function initQuoteRecipients() {
    document.querySelectorAll('[data-quote-recipients]').forEach(root => {
        if (root.dataset.bound) return;
        root.dataset.bound = 'true';
        const form = root.closest('form');
        const boxes = [...root.querySelectorAll('input[name="recipient_keys[]"]')];
        const companies = [...root.querySelectorAll('[data-recipient-company]')];
        const search = root.querySelector('[data-recipient-search]');
        const selectedOnly = root.querySelector('[data-recipient-selected-only]');
        const group = root.querySelector('[data-recipient-group]');
        const result = root.querySelector('[data-recipient-group-result]');
        const count = form.querySelector('[data-recipient-count]');
        const error = root.querySelector('[data-recipient-error]');
        const limit = Number(root.dataset.maxRecipients);
        const readGroup = () => new Set(JSON.parse(group?.selectedOptions[0]?.dataset.recipients || '[]'));
        let groupKeys = root.hasAttribute('data-initial-recipients') ? new Set(JSON.parse(root.dataset.initialRecipients)) : readGroup();
        const manualChoices = new Map();
        const selection = () => boxes.filter(box => box.checked && !box.disabled);

        /** Un cambio di gruppo può sostituire le proposte automatiche, mentre le scelte esplicite hanno la precedenza. */
        function rememberChoice(box) {
            if (box.checked === groupKeys.has(box.value)) manualChoices.delete(box.value);
            else manualChoices.set(box.value, box.checked);
        }
        boxes.filter(box => !box.disabled).forEach(rememberChoice);

        function filterList() {
            const query = search.value.trim().toLocaleLowerCase('it');
            const onlyChecked = selectedOnly.getAttribute('aria-pressed') === 'true';
            companies.forEach(company => {
                const matchesCompany = company.dataset.recipientCompany.toLocaleLowerCase('it').includes(query);
                const options = [...company.querySelectorAll('[data-recipient-option]')];
                options.forEach(option => {
                    const matches = matchesCompany || option.dataset.recipientOption.toLocaleLowerCase('it').includes(query);
                    option.hidden = !matches || (onlyChecked && !option.querySelector('input').checked);
                });
                company.hidden = options.every(option => option.hidden);
                const agencies = company.querySelector('.recipient-agencies');
                if (!agencies) return;
                const visibleAgency = agencies.querySelector('[data-recipient-option]:not([hidden])');
                agencies.hidden = !visibleAgency;
                if (visibleAgency && (query || onlyChecked || agencies.querySelector('input:checked'))) agencies.open = true;
            });
            root.querySelector('[data-recipient-empty]').hidden = !companies.length || companies.some(company => !company.hidden);
        }

        function refresh() {
            const total = selection().length;
            count.textContent = total === 1 ? '1 destinatario selezionato' : total + ' destinatari selezionati';
            selectedOnly.disabled = !total;
            if (!total) selectedOnly.setAttribute('aria-pressed', 'false');
            error.hidden = true;
            filterList();
        }

        group?.addEventListener('change', () => {
            groupKeys = readGroup();
            boxes.forEach(box => { box.checked = !box.disabled && (manualChoices.get(box.value) ?? groupKeys.has(box.value)); });
            const available = new Set(boxes.filter(box => !box.disabled).map(box => box.value));
            const missing = [...groupKeys].filter(key => !available.has(key)).length;
            result.textContent = missing ? (missing === 1 ? 'Un destinatario del gruppo non è disponibile.' : missing + ' destinatari del gruppo non sono disponibili.') : '';
            search.value = '';
            selectedOnly.setAttribute('aria-pressed', 'false');
            refresh();
        });
        boxes.forEach(box => box.addEventListener('change', () => {
            rememberChoice(box);
            refresh();
        }));
        search.addEventListener('input', filterList);
        selectedOnly.addEventListener('click', () => {
            selectedOnly.setAttribute('aria-pressed', String(selectedOnly.getAttribute('aria-pressed') !== 'true'));
            filterList();
        });
        if (form.matches('.quote-dispatch')) {
            const subject = form.elements.namedItem('subject');
            subject.addEventListener('input', () => { form.querySelector('[data-dispatch-subject]').textContent = subject.value; });
            form.addEventListener('change', event => {
                if (!event.target.matches('input[type="file"], [name="document_ids[]"]')) return;
                const existing = form.querySelectorAll('[name="document_ids[]"]:checked').length;
                const uploads = [...form.querySelectorAll('input[type="file"]')].reduce((sum, input) => sum + input.files.length, 0);
                const total = existing + uploads;
                form.querySelector('[data-dispatch-files]').textContent = total ? total + ' in anteprima' : 'Facoltativi';
            });
            form.addEventListener('invalid', event => {
                const section = event.target.closest('.dispatch-disclosure');
                if (section) section.open = true;
            }, true);
        }
        form.addEventListener('submit', event => {
            const total = selection().length;
            if (total > 0 && total <= limit) return;
            event.preventDefault();
            error.textContent = total ? 'Scegli al massimo ' + limit + ' destinatari.' : 'Spunta almeno una compagnia o un’agenzia per proseguire.';
            error.hidden = false;
            error.focus();
        });
        refresh();
    });
}

document.addEventListener('DOMContentLoaded', initQuoteRecipients);
document.addEventListener('livewire:navigated', initQuoteRecipients);
