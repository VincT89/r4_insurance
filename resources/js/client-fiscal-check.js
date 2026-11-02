function initClientFiscalChecks() {
    document.querySelectorAll('[data-fiscal-check]').forEach(root => {
        if (root.dataset.fiscalBound) return;
        root.dataset.fiscalBound = 'true';
        const fields = [...root.querySelectorAll('[data-fiscal-field]')];
        const feedback = root.querySelector('[data-fiscal-feedback]');
        const status = root.querySelector('[data-fiscal-status]');
        const errors = root.querySelector('[data-fiscal-errors]');
        const matches = root.querySelector('[data-fiscal-matches]');
        let timer, pending, generation = 0;

        function clear() {
            clearTimeout(timer);
            pending?.abort();
            generation++;
            feedback.hidden = true;
            errors.replaceChildren();
            matches.replaceChildren();
        }

        async function check() {
            clear();
            const values = Object.fromEntries(fields.map(field => [field.dataset.fiscalField, field.value.trim()]));
            if (!Object.values(values).some(Boolean)) return;
            if (root.dataset.currentClient) values.client_id = Number(root.dataset.currentClient);
            const current = generation;
            pending = new AbortController();
            feedback.hidden = false;
            status.textContent = 'Controllo dei dati fiscali…';
            try {
                const response = await fetch(root.dataset.checkUrl, {
                    method: 'POST', credentials: 'same-origin', signal: pending.signal,
                    headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
                    body: JSON.stringify(values),
                });
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (generation !== current || !root.isConnected) return;
                status.textContent = data.message;
                for (const message of Object.values(data.errors).flat()) {
                    const item = document.createElement('li');
                    item.textContent = message;
                    errors.append(item);
                }
                for (const client of data.matches) {
                    const canChoose = root.hasAttribute('data-fiscal-picker') && data.status === 'duplicate' && !Object.keys(data.errors).length;
                    const action = document.createElement(canChoose ? 'button' : 'a');
                    action.className = 'btn btn-g btn-sm';
                    const reason = client.fields.includes('vat_number') ? 'stessa partita IVA' : 'stesso codice fiscale';
                    action.textContent = `${canChoose ? 'Usa' : 'Apri'} ${client.name} (${reason})`;
                    if (canChoose) {
                        action.type = 'button';
                        action.addEventListener('click', () => root.dispatchEvent(new CustomEvent('client-fiscal-selected', {bubbles: true, detail: client})));
                    } else {
                        action.href = client.url;
                        action.target = '_blank';
                        action.rel = 'noopener';
                        action.setAttribute('aria-label', `${action.textContent}, in una nuova scheda`);
                    }
                    matches.append(action);
                }
            } catch (error) {
                if (error.name !== 'AbortError' && generation === current && root.isConnected) status.textContent = 'Controllo momentaneamente non disponibile. I dati saranno ricontrollati quando salvi.';
            }
        }

        fields.forEach(field => {
            field.addEventListener('input', () => {
                clear();
                const complete = fields.every(input => {
                    const value = input.value.replace(/\s/g, '').toUpperCase();
                    return !value || (input.dataset.fiscalField === 'vat_number' ? /^(IT)?\d{11}$/.test(value) : [11, 16].includes(value.length));
                });
                if (complete) timer = setTimeout(check, 600);
            });
            field.addEventListener('blur', check);
        });
        root.addEventListener('client-fiscal-reset', clear);
    });
}

document.addEventListener('DOMContentLoaded', initClientFiscalChecks);
document.addEventListener('livewire:navigated', initClientFiscalChecks);
