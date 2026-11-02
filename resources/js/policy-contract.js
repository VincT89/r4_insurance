import {parsePolicyContract, contractLabels} from './policy-contract-parser.js';

const scheduleNames = ['starts_on','expires_on','premium','installment_premium','payment_frequency','payment_method','status'];
const money = cents => new Intl.NumberFormat('it-IT', {style:'currency', currency:'EUR'}).format(cents / 100);
const date = value => /^\d{4}-\d{2}-\d{2}$/.test(value) ? value.split('-').reverse().join('/') : value;
const tick = () => new Promise(resolve => requestAnimationFrame(resolve));

function initPolicyContracts() {
    document.querySelectorAll('[data-policy-contract]').forEach(form => {
        if (form.dataset.contractBound) return;
        form.dataset.contractBound = 'true';
        const fileInput = form.elements.contract, fields = form.querySelector('[data-contract-fields]');
        const status = form.querySelector('[data-contract-status]'), warnings = form.querySelector('[data-contract-warnings]');
        const save = form.querySelector('[data-contract-save]'), manual = form.querySelector('[data-contract-manual]');
        const schedule = form.querySelector('[data-contract-schedule]'), scheduleStatus = form.querySelector('[data-contract-schedule-status]');
        const fingerprint = form.querySelector('[data-contract-fingerprint]'), refresh = form.querySelector('[data-refresh-contract-schedule]');
        const errors = form.querySelector('[data-contract-errors]'), review = form.elements.contract_reviewed;
        const catalog = JSON.parse(form.querySelector('[data-contract-catalog]').textContent);
        const transition = form.hasAttribute('data-policy-transition'), saveLabel = save.textContent;
        const initial = Object.fromEntries(Object.keys(contractLabels).map(name => [name, form.elements.namedItem(name)?.value || '']));
        let generation = 0, reading = false, submitting = false, readerAbort, scheduleAbort, scheduleGeneration = 0, scheduleTimer;
        let autoValues = {}, validFile = false;
        function enableSave() { save.disabled = submitting || reading || !validFile || ((transition || form.elements.titles_mode.value === 'automatic') && !fingerprint.value); }
        function addWarning(message) { const item = document.createElement('li'); item.textContent = message; warnings.append(item); warnings.hidden = false; }
        function showFields() { fields.hidden = false; form.querySelector('[data-contract-initial-cancel]').hidden = true; }
        function fieldNote(field, message) {
            if (!field) return;
            const parent = field.closest('.form-g');
            if (!parent) return;
            let note = parent.querySelector('.contract-field-note');
            if (!note) { note = document.createElement('p'); note.className = 'contract-field-note'; parent.append(note); }
            note.textContent = message;
        }
        function invalidateSchedule() {
            scheduleGeneration++; scheduleAbort?.abort(); clearTimeout(scheduleTimer);
            fingerprint.value = ''; schedule.hidden = true; review.checked = false; enableSave();
            scheduleTimer = setTimeout(renderSchedule, 350);
        }
        async function renderSchedule() {
            clearTimeout(scheduleTimer);
            const manualTitles = form.elements.titles_mode.value === 'manual';
            refresh.hidden = manualTitles && !transition; schedule.hidden = true; fingerprint.value = ''; enableSave();
            if (manualTitles && !transition) { scheduleStatus.textContent = 'La polizza sarà salvata senza titoli. Potrai inserirli dalla sezione Titoli e pagamenti.'; return; }
            const values = transition ? Object.fromEntries([...new FormData(form)].filter(([name,value]) => typeof value === 'string' && !['cancel_titles[]','contract_reviewed','schedule_fingerprint'].includes(name)).map(([name,value]) => [name,value || null])) : Object.fromEntries(scheduleNames.map(name => [name, form.elements.namedItem(name)?.value || null]));
            if (transition) values.cancel_titles = [...form.querySelectorAll('[name="cancel_titles[]"]:checked')].map(field => field.value);
            if (scheduleNames.filter(name => name !== 'installment_premium').some(name => !values[name])) {
                scheduleStatus.textContent = 'Completa decorrenza, scadenza, premio totale, frazionamento e metodo di pagamento per vedere i titoli.'; return;
            }
            const current = ++scheduleGeneration;
            scheduleAbort?.abort(); scheduleAbort = new AbortController();
            scheduleStatus.textContent = 'Calcolo dei titoli…';
            try {
                const response = await fetch(form.dataset.scheduleUrl, {method:'POST', credentials:'same-origin', signal:scheduleAbort.signal, headers:{Accept:'application/json', 'Content-Type':'application/json', 'X-CSRF-TOKEN':form.elements._token.value}, body:JSON.stringify(values)});
                const data = await response.json();
                if (current !== scheduleGeneration || !form.isConnected) return;
                if (!response.ok) { scheduleStatus.textContent = Object.values(data.errors || {}).flat().join(' ') || 'Impossibile calcolare i titoli. Riprova.'; return; }
                const table = document.createElement('table'), caption = document.createElement('caption'), head = document.createElement('thead'), body = document.createElement('tbody');
                const receipts = data.rows.filter(row => row.direction !== 'refund').reduce((sum,row) => sum + row.amount_cents, 0), refunds = data.rows.filter(row => row.direction === 'refund').reduce((sum,row) => sum + row.amount_cents, 0);
                caption.textContent = `${data.rows.length} ${data.rows.length === 1 ? 'titolo' : 'titoli'} / Da incassare ${money(receipts)}${refunds ? ` / Da rimborsare ${money(refunds)}` : ''}`;
                const header = document.createElement('tr');
                for (const [label, cls] of [['Scadenza',''],['Copertura','contract-coverage'],['Importo','']]) { const cell = document.createElement('th'); cell.scope = 'col'; cell.textContent = label; cell.className = cls; header.append(cell); }
                head.append(header);
                for (const row of data.rows) {
                    const tr = document.createElement('tr');
                    for (const [value, cls] of [[date(row.due_on),''],[row.kind === 'adjustment' ? 'Conguaglio separato' : `${date(row.coverage_from)} – ${date(row.coverage_to)}`,'contract-coverage'],[`${money(row.amount_cents)}${row.direction === 'refund' ? ' da rimborsare' : ''}`,'']]) { const td = document.createElement('td'); td.textContent = value; td.className = cls; tr.append(td); }
                    body.append(tr);
                }
                table.append(caption, head, body); schedule.replaceChildren(table); schedule.hidden = false;
                fingerprint.value = data.fingerprint;
                scheduleStatus.textContent = `${manualTitles ? 'I titoli ordinari andranno inseriti manualmente. Gli eventuali conguagli elencati saranno creati alla conferma.' : 'Questi titoli saranno creati alla conferma.'} Incassi e rimborsi effettivi si registrano quando avvengono.${data.cancelled_cents ? ` Residui della vecchia polizza da annullare: ${money(data.cancelled_cents)}. Gli incassi ricevuti restano conservati.` : ''}`;
            } catch (error) { if (error.name !== 'AbortError' && current === scheduleGeneration) scheduleStatus.textContent = 'Calcolo non disponibile. Controlla la connessione e premi Ricalcola titoli.'; }
            finally { if (current === scheduleGeneration) enableSave(); }
        }
        function differences() {
            if (!catalog.quote) return;
            const root = form.querySelector('[data-contract-differences]'), list = form.querySelector('[data-contract-difference-list]');
            list.replaceChildren();
            for (const [name, before] of Object.entries(catalog.quote)) {
                if (['merit_class_cu','merit_class_internal','installment_premium'].includes(name)) continue;
                const field = form.elements.namedItem(name), after = field?.value || '';
                const same = name === 'premium' ? Math.round(Number(before) * 100) === Math.round(Number(after) * 100) : String(before ?? '') === after;
                if (same) continue;
                const item = document.createElement('li');
                const display = value => name === 'premium' ? money(Math.round(Number(value) * 100)) : name.endsWith('_on') ? date(value) : name === 'supplier_agency_id' ? Object.values(catalog.agencies).flat().find(agency => String(agency.id) === String(value))?.name || 'Non indicata' : value || 'Non indicato';
                item.textContent = `${contractLabels[name] || 'Veicolo collegato'}: preventivo ${display(before)}; contratto ${display(after)}.`;
                list.append(item);
            }
            root.hidden = !list.children.length;
            form.elements.quote_changes_confirmed.required = !root.hidden;
            form.elements.quote_changes_confirmed.checked = false;
        }
        async function setField(name, value) {
            const field = form.elements.namedItem(name);
            if (!field) return false;
            if (field.tagName === 'SELECT') {
                const option = [...field.options].find(option => option.value.toLocaleUpperCase('it') === String(value).toLocaleUpperCase('it'));
                if (!option) { addWarning(`${contractLabels[name]}: “${value}” non è tra le scelte disponibili. Seleziona la voce corretta.`); return false; }
                value = option.value;
            }
            if (field.value && (form.dataset.restore === 'true' || (field.value !== initial[name] && field.value !== autoValues[name]))) {
                if (field.value !== value) addWarning(`${contractLabels[name]}: nel contratto risulta “${value}”; il dato modificato nel modulo è stato conservato.`);
                return false;
            }
            field.value = value; autoValues[name] = value;
            field.dispatchEvent(new Event('input', {bubbles:true})); field.dispatchEvent(new Event('change', {bubbles:true}));
            if (['company','category'].includes(name)) await tick();
            return true;
        }
        async function readFile() {
            const current = ++generation;
            readerAbort?.abort(); readerAbort = new AbortController();
            reading = false; validFile = false; review.checked = false; warnings.replaceChildren(); warnings.hidden = true;
            form.querySelector('[data-contract-source]').hidden = true; manual.hidden = true;
            enableSave();
            const file = fileInput.files?.[0];
            if (!file) { status.textContent = 'Carica il contratto PDF per poter confermare la polizza.'; return; }
            if (!/\.pdf$/i.test(file.name) || (file.type && file.type !== 'application/pdf') || file.size > 10 * 1024 * 1024) { status.textContent = 'Scegli un file PDF di massimo 10 MB.'; return; }
            validFile = true; reading = true; manual.hidden = false; enableSave();
            const choice = form.querySelector('[data-contract-quote-choice]'); if (choice) choice.hidden = true;
            for (const [name,value] of Object.entries(autoValues)) {
                const field = form.elements.namedItem(name);
                if (field?.value === value) { field.value = initial[name]; field.dispatchEvent(new Event('change', {bubbles:true})); }
            }
            autoValues = {}; form.querySelectorAll('.contract-field-note').forEach(note => note.remove());
            form.querySelector('[data-policy-client-picker]')?.dispatchEvent(new Event('contract-client-reset'));
            invalidateSchedule();
            status.textContent = 'Lettura del contratto in corso…';
            try {
                const {readPolicyContract} = await import('./policy-contract-reader.js');
                const result = await readPolicyContract(file, form.dataset.ocrBase, message => { if (current === generation) status.textContent = message; }, readerAbort.signal);
                if (current !== generation || !form.isConnected) return;
                const parsed = parsePolicyContract(result.text, catalog);
                for (const name of ['category','company', ...Object.keys(parsed.values).filter(name => !['category','company'].includes(name))]) {
                    if (current !== generation) return;
                    if (parsed.values[name] !== undefined) await setField(name, parsed.values[name]);
                }
                for (const message of parsed.warnings) addWarning(message);
                if (result.skipped) addWarning(`${result.skipped} pagine non sono state lette automaticamente. Controlla il documento completo.`);
                const picker = form.querySelector('[data-policy-client-picker]');
                const selectedName = picker?.querySelector('[name="client_id"]')?.value ? picker.querySelector('[data-client-search]').value : catalog.client?.name;
                if (selectedName && parsed.client.name && selectedName.trim().toLocaleUpperCase('it') !== parsed.client.name.trim().toLocaleUpperCase('it')) addWarning(`Contraente da verificare: nel contratto è indicato “${parsed.client.name}”, mentre hai selezionato “${selectedName}”.`);
                picker?.dispatchEvent(new CustomEvent('contract-client-read', {detail:parsed.client}));
                form.querySelector('[data-contract-text]').textContent = result.text || 'Nessun testo leggibile trovato.';
                form.querySelector('[data-contract-source]').hidden = false;
                const missing = ['number','category','company','starts_on','expires_on','premium','payment_frequency','payment_method'].filter(name => !form.elements.namedItem(name)?.value);
                for (const name of missing) fieldNote(form.elements.namedItem(name), 'Da completare: dato non riconosciuto nel contratto.');
                if (missing.length) addWarning(`Completa: ${missing.map(name => contractLabels[name]).join(', ')}.`);
                const count = Object.keys(autoValues).length;
                status.textContent = count ? `Lettura completata: ${count} campi compilati. Controlla i dati prima di confermare.` : 'Nessun dato riconosciuto con certezza. Puoi compilare i campi controllando il contratto.';
            } catch (error) {
                if (current !== generation) return;
                status.textContent = error.message?.startsWith('Il PDF') || error.message?.startsWith('La lettura') ? error.message : 'Non è stato possibile leggere il PDF. Puoi riprovare con una copia leggibile oppure compilare i dati controllando il contratto.';
            } finally {
                if (current === generation) { reading = false; manual.hidden = true; showFields(); differences(); await renderSchedule(); enableSave(); }
            }
        }
        fileInput.addEventListener('change', readFile);
        manual.addEventListener('click', () => { generation++; readerAbort?.abort(); reading = false; manual.hidden = true; showFields(); status.textContent = 'Lettura interrotta. Completa i dati controllando il contratto.'; void renderSchedule(); enableSave(); });
        refresh.addEventListener('click', () => { review.checked = false; void renderSchedule(); });
        form.addEventListener('input', event => {
            if (!['contract_reviewed','quote_changes_confirmed'].includes(event.target.name)) review.checked = false;
            if (scheduleNames.includes(event.target.name) || (transition && !['contract','contract_reviewed','quote_changes_confirmed'].includes(event.target.name))) invalidateSchedule();
            if (Object.hasOwn(catalog.quote || {}, event.target.name)) differences();
        });
        form.addEventListener('change', event => { if (event.target.name === 'titles_mode' || (transition && !['contract','contract_reviewed','quote_changes_confirmed'].includes(event.target.name))) invalidateSchedule(); });
        form.querySelector('[data-use-contract-quote]')?.addEventListener('click', () => {
            const select = form.querySelector('[data-contract-quote]');
            if (select.value) window.location.assign(select.value); else select.focus();
        });
        form.addEventListener('submit', async event => {
            if (event.defaultPrevented) return;
            event.preventDefault(); errors.hidden = true;
            if (submitting || save.disabled) return;
            if (!form.reportValidity()) return;
            submitting = true; enableSave(); save.textContent = 'Creazione in corso…';
            try {
                const response = await fetch(form.action, {method:'POST', credentials:'same-origin', headers:{Accept:'application/json', 'X-CSRF-TOKEN':form.elements._token.value}, body:new FormData(form)});
                const data = await response.json();
                if (!response.ok) {
                    const messages = Object.values(data.errors || {}).flat();
                    errors.replaceChildren();
                    for (const message of messages.length ? messages : ['Salvataggio non riuscito. Verifica la sessione e riprova.']) { const p = document.createElement('p'); p.textContent = message; errors.append(p); }
                    errors.hidden = false; errors.focus();
                    if (data.errors?.schedule_fingerprint) invalidateSchedule();
                    return;
                }
                window.location.assign(data.url);
            } catch { errors.textContent = 'Non è arrivata la conferma del salvataggio. Controlla l’elenco polizze in un’altra scheda prima di riprovare; i dati di questo modulo sono conservati.'; errors.hidden = false; errors.focus(); }
            finally { submitting = false; save.textContent = saveLabel; enableSave(); }
        });
        if (form.dataset.restore === 'true') { showFields(); status.textContent = 'I dati inseriti sono conservati. Ricarica il contratto PDF e controllali prima di confermare.'; }
        form.addEventListener('client-context-loaded', () => { review.checked = false; differences(); });
        window.addEventListener('pagehide', () => { readerAbort?.abort(); scheduleAbort?.abort(); clearTimeout(scheduleTimer); }, {once:true});
        differences();
    });
}

document.addEventListener('DOMContentLoaded', initPolicyContracts);
document.addEventListener('livewire:navigated', initPolicyContracts);
