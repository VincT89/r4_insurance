import {matchingContractClient} from './policy-contract-preparation.js';

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
        const keep = root.querySelector('[data-keep-client]');
        const deferred = root.hasAttribute('data-deferred-client');
        const requester = form.querySelector('[data-quote-requester]');
        let creatingClient = false;
        let contractClientValues = {};
        function deferClient(enabled) {
            creatingClient = deferred && enabled;
            for (const field of quick?.querySelectorAll('[data-quick-field]') || []) {
                if (creatingClient) field.name = `new_client[${field.dataset.quickField}]`;
                else field.removeAttribute('name');
                if (field.dataset.quickField === 'name') field.required = creatingClient;
            }
            root.dataset.creatingClient = String(creatingClient);
            if (creatingClient) { selected.value = ''; selectedName = ''; search.value = ''; form.dataset.clientPending = 'false'; status.textContent = 'Nuovo cliente: controlla i dati. Sarà salvato insieme alla polizza.'; }
        }
        let generation = 0, timer, pending, loading = false;
        let selectedName = selected.value ? search.value : '';
        function showSelected() {
            search.value = selectedName;
            results.replaceChildren();
            if (keep) keep.hidden = true;
            form.dataset.clientPending = 'false';
            status.textContent = selected.value ? `Cliente selezionato: ${selectedName}` : 'Seleziona o crea un cliente.';
        }
        function hasLinkedData() {
            return ['vehicle_id','client_asset_id','current_policy_id','merit_class_cu','merit_class_internal','quote_recipient_group_id'].some(name => form.elements.namedItem(name)?.value)
                || [...form.querySelectorAll('input[type="file"]')].some(input => input.files?.length)
                || [...form.querySelectorAll('input[name="recipient_keys[]"]')].some(input => input.checked)
                || [...form.querySelectorAll('[data-quick-vehicle-field]')].some(input => input.value);
        }
        function resetLinks({preserveRecipients = false} = {}) {
            for (const [name, label] of [['vehicle_id', 'Nessun veicolo collegato'], ['client_asset_id', 'Nessun bene collegato'], ['current_policy_id', 'Nessuna polizza selezionata']]) {
                const select = form.elements.namedItem(name);
                if (select) { select.replaceChildren(new Option(label, '')); if (name==='current_policy_id') select.dispatchEvent(new Event('change',{bubbles:true})); }
            }
            form.dispatchEvent(new CustomEvent('client-context-cleared', {detail: {preserveRecipients}}));
        }
        async function choose(client) {
            if (String(client.id) === selected.value) { ++generation; pending?.abort(); loading = false; showSelected(); return true; }
            const replacingClient = Boolean(selected.value);
            const customRequester = Boolean(requester?.value.trim() && requester.value.trim() !== selectedName);
            const linked = replacingClient && hasLinkedData();
            if (replacingClient && (linked || customRequester)) {
                const changes = [];
                if (deferred) changes.push('Verifica il contratto caricato e riseleziona il veicolo o il bene collegato.');
                else if (linked) changes.push('Veicolo, polizza, classe, destinatari e allegati selezionati verranno rimossi dalla compilazione.');
                if (customRequester) changes.push('Il richiedente prenderà il nome del nuovo cliente.');
                if (!window.confirm(`Cambiare cliente da ${selectedName} a ${client.name}? ${changes.join(' ')}`)) {
                    ++generation; pending?.abort(); loading = false; showSelected(); return false;
                }
            }
            const current = ++generation;
            pending?.abort();
            clearTimeout(timer);
            loading = true;
            form.dataset.clientPending = 'true';
            search.value = client.name;
            results.replaceChildren();
            status.textContent = 'Caricamento dati del cliente…';
            try {
                const response = await fetch(`${root.dataset.contextUrl}/${encodeURIComponent(client.id)}/policy-context`, {headers: {Accept: 'application/json'}, credentials: 'same-origin'});
                if (!response.ok) throw new Error();
                const data = await response.json();
                if (current !== generation) return;
                /** Conserva il lavoro precedente finché il nuovo cliente non è stato caricato correttamente. */
                /** La prima scelta del cliente conserva i destinatari già spuntati; un cambio cliente li azzera. */
                resetLinks({preserveRecipients: !replacingClient});
                deferClient(false);
                selected.value = String(client.id);
                selectedName = client.name;
                if (requester && (replacingClient || !customRequester)) requester.value = client.name;
                for (const vehicle of data.vehicles) {
                    const option = new Option([vehicle.plate, vehicle.make, vehicle.model].filter(Boolean).join(' / '), vehicle.id);
                    option.dataset.cu = vehicle.merit_class_cu || '';
                    option.dataset.internal = vehicle.merit_class_internal || '';
                    option.dataset.plate = vehicle.plate;
                    form.elements.vehicle_id?.add(option);
                }
                for (const asset of data.assets) form.elements.client_asset_id?.add(new Option(`${asset.name} / ${asset.category}`, asset.id));
                for (const policy of data.policies || []) {
                    const option = new Option(`${policy.number} / ${policy.company} / ${policy.category}`, policy.id);
                    option.dataset.branch = policy.insurance_branch_id || '';
                    option.dataset.company = policy.supplier_company_id || '';
                    option.dataset.subbranch = policy.insurance_subbranch_id || '';
                    option.dataset.vehicle = policy.vehicle_id || '';
                    option.dataset.cu = policy.merit_class_cu || '';
                    option.dataset.internal = policy.merit_class_internal || '';
                    form.elements.current_policy_id?.add(option);
                }
                showSelected();
                form.dispatchEvent(new CustomEvent('client-context-loaded',{detail:{client:data.client}}));
                if (quick) { quick.hidden = true; quick.dispatchEvent(new Event('client-fiscal-reset')); }
                root.querySelector('[data-new-client]')?.setAttribute('aria-expanded', 'false');
                return true;
            } catch {
                if (current === generation) {
                    status.textContent = 'Impossibile caricare il cliente. I dati precedenti sono conservati: riprova o mantieni il cliente selezionato.';
                    if (keep) keep.hidden = !selected.value;
                }
                return false;
            } finally {
                if (current === generation) loading = false;
            }
        }
        search.addEventListener('input', () => {
            if (creatingClient) { deferClient(false); quick.hidden = true; }
            const current = ++generation;
            loading = false;
            form.dataset.clientPending = String(!selected.value || search.value !== selectedName);
            if (keep) keep.hidden = !selected.value || search.value === selectedName;
            clearTimeout(timer);
            pending?.abort();
            results.replaceChildren();
            status.textContent = selected.value ? `Dati di ${selectedName} conservati. Seleziona un risultato per cambiare cliente oppure mantieni quello attuale.` : 'Seleziona un risultato per collegare il cliente.';
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
                        button.textContent = [client.name, client.vat_number || client.tax_code, client.email].filter(Boolean).join(' · ');
                        button.addEventListener('click', () => choose(client)); results.append(button);
                    }
                    if (!clients.length) status.textContent = 'Nessun cliente trovato. Puoi crearne uno.';
                } catch (error) { if (error.name !== 'AbortError' && generation === current) status.textContent = 'Ricerca non disponibile. Riprova.'; }
            }, 250);
        });
        keep?.addEventListener('click', () => { ++generation; clearTimeout(timer); pending?.abort(); loading = false; showSelected(); });
        root.querySelector('[data-new-client]')?.addEventListener('click', (event) => {
            if (deferred && quick.hidden && selected.value && !window.confirm('Usare un nuovo contraente per questa polizza? Controlla che corrisponda al contratto caricato.')) return;
            quick.hidden = !quick.hidden;
            if (deferred) { if (!quick.hidden) resetLinks(); deferClient(!quick.hidden); }
            event.currentTarget.setAttribute('aria-expanded', String(!quick.hidden));
            if (!quick.hidden) quick.querySelector('[data-quick-field="name"]').focus();
        });
        root.addEventListener('client-fiscal-selected', (event) => choose(event.detail));
        root.addEventListener('client-fiscal-checked', event=>{
            if(!deferred || !creatingClient || loading) return;
            const values=Object.fromEntries([...quick.querySelectorAll('[data-quick-field]')].map(field=>[field.dataset.quickField,field.value]));
            const existing=matchingContractClient(event.detail,contractClientValues,values);
            if(existing) void choose(existing);
        });
        root.addEventListener('contract-client-reset', () => {
            for (const field of quick?.querySelectorAll('[data-quick-field]') || []) {
                if (field.value === contractClientValues[field.dataset.quickField]) field.value = field.dataset.quickField === 'client_type' ? 'person' : '';
            }
            contractClientValues = {};
        });
        root.addEventListener('contract-client-read', event => {
            if (!deferred || !quick || selected.value || search.value.trim()) return;
            const values = event.detail;
            if (!Object.keys(values).length) return;
            quick.hidden = false; deferClient(true);
            root.querySelector('[data-new-client]')?.setAttribute('aria-expanded', 'true');
            for (const field of quick.querySelectorAll('[data-quick-field]')) {
                const name = field.dataset.quickField;
                if (!field.value || field.value === contractClientValues[name] || (name === 'client_type' && !Object.keys(contractClientValues).length)) {
                    field.value = values[name] || (name === 'client_type' ? 'person' : '');
                    field.dispatchEvent(new Event('input', {bubbles:true}));
                }
            }
            contractClientValues = {...values};
        });
        const oldClient = root.querySelector('[data-contract-old-client]');
        if (oldClient) root.dispatchEvent(new CustomEvent('contract-client-read', {detail:JSON.parse(oldClient.textContent)}));
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
                if (await choose(body)) quick.querySelectorAll('input').forEach(input => { input.value = ''; });
            } catch { error.textContent = 'Connessione interrotta: cerca il cliente prima di riprovare, per evitare duplicati.'; }
            finally { button.disabled = false; }
        });
        form.addEventListener('submit', (event) => { if ((!selected.value && !creatingClient) || loading || form.dataset.clientPending === 'true') { event.preventDefault(); status.textContent = 'Conferma il cliente dai risultati oppure mantieni quello attuale prima di salvare.'; search.focus(); } });
    });
}
document.addEventListener('DOMContentLoaded', initPolicyClientPicker);
document.addEventListener('livewire:navigated', initPolicyClientPicker);
