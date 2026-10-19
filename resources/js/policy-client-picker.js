function initPolicyClientPicker() {
    document.querySelectorAll('[data-policy-client-picker]').forEach((root) => {
        if (root.dataset.bound) return;
        root.dataset.bound = 'true';
        const form = root.closest('form');
        const search = root.querySelector('[data-client-search]');
        const selected = root.querySelector('[name="client_id"]');
        const results = root.querySelector('[data-client-results]');
        const status = root.querySelector('[data-client-selected]');
        const quick = root.querySelector('[data-quick-client]');
        let generation = 0, timer, pending;
        function resetLinks() {
            for (const [name, label] of [['vehicle_id', 'Nessun veicolo collegato'], ['client_asset_id', 'Nessun bene collegato'], ['current_policy_id', 'Nessuna polizza selezionata']]) {
                const select = form.elements.namedItem(name);
                if (select) { select.replaceChildren(new Option(label, '')); if (name==='current_policy_id') select.dispatchEvent(new Event('change',{bubbles:true})); }
            }
        }
        async function choose(client) {
            const current = ++generation;
            pending?.abort();
            selected.value = '';
            search.value = client.name;
            results.replaceChildren();
            resetLinks();
            status.textContent = 'Caricamento dati del cliente…';
            try {
                const response = await fetch(`${root.dataset.contextUrl}/${encodeURIComponent(client.id)}/policy-context`, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (current !== generation) return;
                selected.value = String(client.id);
                for (const vehicle of data.vehicles) form.elements.vehicle_id?.add(new Option([vehicle.plate, vehicle.make, vehicle.model].filter(Boolean).join(' / '), vehicle.id));
                for (const asset of data.assets) form.elements.client_asset_id?.add(new Option(`${asset.name} / ${asset.category}`, asset.id));
                for (const policy of data.policies || []) form.elements.current_policy_id?.add(new Option(`${policy.number} / ${policy.company} / ${policy.category}`, policy.id));
                status.textContent = `Cliente selezionato: ${client.name}`;
                if (quick) quick.hidden = true;
                root.querySelector('[data-new-client]')?.setAttribute('aria-expanded', 'false');
            } catch {
                if (current === generation) status.textContent = 'Impossibile selezionare questo cliente. Controlla i permessi o riprova.';
            }
        }
        search.addEventListener('input', () => {
            const current = ++generation;
            selected.value = '';
            resetLinks();
            clearTimeout(timer);
            pending?.abort();
            results.replaceChildren();
            status.textContent = 'Seleziona un risultato per collegare il cliente.';
            if (search.value.trim().length < 2) return;
            timer = setTimeout(async () => {
                pending = new AbortController();
                try {
                    const url = new URL(root.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', search.value.trim());
                    const response = await fetch(url, {headers: {Accept: 'application/json'}, signal: pending.signal, credentials: 'same-origin'});
                    if (!response.ok) throw new Error();
                    const clients = await response.json();
                    if (generation !== current) return;
                    results.replaceChildren();
                    for (const client of clients) {
                        const button = document.createElement('button');
                        button.type = 'button'; button.className = 'client-picker-result';
                        button.textContent = [client.name, client.tax_code || client.vat_number, client.email].filter(Boolean).join(' · ');
                        button.addEventListener('click', () => choose(client)); results.append(button);
                    }
                    if (!clients.length) status.textContent = 'Nessun cliente trovato. Puoi crearne uno.';
                } catch (error) { if (error.name !== 'AbortError' && generation === current) status.textContent = 'Ricerca non disponibile. Riprova.'; }
            }, 250);
        });
        root.querySelector('[data-new-client]')?.addEventListener('click', (event) => {
            quick.hidden = !quick.hidden;
            event.currentTarget.setAttribute('aria-expanded', String(!quick.hidden));
            if (!quick.hidden) quick.querySelector('[data-quick-field="name"]').focus();
        });
        root.querySelector('[data-save-client]')?.addEventListener('click', async (event) => {
            const button = event.currentTarget, error = root.querySelector('[data-quick-error]');
            const data = Object.fromEntries([...quick.querySelectorAll('[data-quick-field]')].map(field => [field.dataset.quickField, field.value.trim() || null]));
            if (!data.name) { error.textContent = 'Inserisci il nome o la ragione sociale.'; return; }
            if (data.client_type === 'company') data.company_name = data.name;
            button.disabled = true; error.textContent = '';
            try {
                const response = await fetch(root.dataset.createUrl, {method: 'POST', credentials: 'same-origin', headers: {Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}, body: JSON.stringify(data)});
                const body = await response.json();
                if (!response.ok) { error.textContent = Object.values(body.errors || {}).flat().join(' ') || 'Impossibile creare il cliente.'; return; }
                await choose(body);
                quick.querySelectorAll('input').forEach(input => { input.value = ''; });
            } catch { error.textContent = 'Connessione interrotta: cerca il cliente prima di riprovare, per evitare duplicati.'; }
            finally { button.disabled = false; }
        });
        form.addEventListener('submit', (event) => { if (!selected.value) { event.preventDefault(); status.textContent = 'Seleziona o crea il contraente prima di salvare.'; search.focus(); } });
    });
}
document.addEventListener('DOMContentLoaded', initPolicyClientPicker);
document.addEventListener('livewire:navigated', initPolicyClientPicker);
