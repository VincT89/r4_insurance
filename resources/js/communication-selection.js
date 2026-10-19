const limit = 500;
const duration = 30 * 60 * 1000;

export class CommunicationSelection {
    constructor(scope, saved, now = Date.now()) {
        this.scope = scope;
        this.items = new Map();
        if (saved?.scope === scope && saved.expires > now && Array.isArray(saved.items)) {
            for (const [id, agency] of saved.items.filter(row => Array.isArray(row) && row.length === 2)) this.add(id, agency);
        }
    }
    add(id, agency) {
        id = String(id); agency = String(agency);
        if (!/^[1-9]\d*$/.test(id) || !/^[1-9]\d*$/.test(agency)) return false;
        if (this.items.get(id) === agency) return true;
        if (this.forAgency(agency).length >= limit) return false;
        this.items.set(id, agency); return true;
    }
    forAgency(agency) { return [...this.items].filter(([, value]) => value === String(agency)).map(([id]) => id); }
    snapshot(now = Date.now()) { return { scope: this.scope, expires: now + duration, items: [...this.items] }; }
}

function initialize(root) {
    if (root.dataset.selectionReady) return;
    root.dataset.selectionReady = '1';
    /** Store only row/agency identifiers, scoped to the account, page and validated search; the server always revalidates before preparing an email. */
    const key = `communication-selection:${root.dataset.selectionUser}:${location.pathname}`;
    let saved, available = true;
    try { saved = JSON.parse(sessionStorage.getItem(key)); } catch { available = false; }
    const selection = new CommunicationSelection(root.dataset.selectionScope, saved);
    const message = root.querySelector('[data-selection-message]');
    if (saved && saved.scope !== selection.scope) message.textContent = 'Filtri cambiati: la precedente selezione è stata azzerata.';
    else if (saved?.expires <= Date.now()) message.textContent = 'La selezione precedente è scaduta: ricontrolla gli elementi da inviare.';
    const boxes = () => [...root.querySelectorAll('input[data-communication-item]')];
    function render() {
        for (const input of boxes()) {
            if (input.disabled) selection.items.delete(input.value);
            else if (selection.items.has(input.value) && selection.items.get(input.value) !== input.dataset.agency) selection.items.delete(input.value);
            input.checked = selection.items.has(input.value) && !input.disabled;
        }
        for (const form of root.querySelectorAll('[data-review-agency]')) {
            const ids = selection.forAgency(form.dataset.reviewAgency);
            form.hidden = !ids.length;
            form.querySelector('[data-agency-count]').textContent = `${ids.length} ${ids.length === 1 ? 'selezionato' : 'selezionati'}`;
            form.querySelector('[data-selection-inputs]').replaceChildren(...ids.map(id => {
                const input = document.createElement('input'); input.type = 'hidden'; input.name = `${root.dataset.selectionField}[]`; input.value = id; return input;
            }));
        }
        root.querySelector('[data-selection-count]').textContent = selection.items.size ? `${selection.items.size} ${selection.items.size === 1 ? 'elemento selezionato' : 'elementi selezionati'} in tutte le pagine` : 'Nessun elemento selezionato';
        root.querySelector('[data-clear-selection]').disabled = !selection.items.size;
        try { sessionStorage.setItem(key, JSON.stringify(selection.snapshot())); } catch { available = false; }
        if (!available) message.textContent = 'Il browser non consente di conservare la selezione: prepara il riepilogo prima di cambiare pagina.';
    }
    root.addEventListener('change', event => {
        const input = event.target.closest('input[data-communication-item]');
        if (!input) return;
        if (!input.checked) selection.items.delete(input.value);
        else if (!selection.add(input.value, input.dataset.agency)) message.textContent = 'Puoi selezionare al massimo 500 elementi per agenzia. Prepara prima questo riepilogo.';
        render();
    });
    root.querySelector('[data-select-page]').addEventListener('click', () => {
        for (const input of boxes().filter(input => !input.disabled && !input.closest('tr')?.hidden)) {
            if (!selection.add(input.value, input.dataset.agency)) message.textContent = 'Raggiunto il limite di 500 elementi per agenzia.';
        }
        render();
    });
    root.querySelector('[data-clear-selection]').addEventListener('click', () => { selection.items.clear(); message.textContent = ''; render(); });
    root.addEventListener('table:rendered', render);
    root.addEventListener('submit', event => {
        const form = event.target.closest('[data-review-agency]');
        if (!form) return;
        render();
        if (!selection.forAgency(form.dataset.reviewAgency).length) event.preventDefault();
    });
    window.addEventListener('pageshow', render);
    render();
}

const init = () => document.querySelectorAll('[data-communication-selection]').forEach(initialize);
document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
