import { exportColumnType } from './table-export-values.js';

const states = new WeakMap();
let serial = 0;
const eligible = root => [...root.querySelectorAll('table.t-table')].filter(t => !t.closest('.appearance-preview'));
const text = value => String(value ?? '').replace(/\s+/g, ' ').trim();
const fold = value => text(value).toLocaleLowerCase('it');
const element = (tag, attributes = {}, content) => {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
    if (content !== undefined) node.textContent = content;
    return node;
};
const button = (label, action) => { const b = element('button', { type: 'button', class: 'btn btn-g btn-sm' }, label); b.addEventListener('click', action); return b; };
export function tableExportUrl(currentUrl, pageName = 'page') {
    const url = new URL(currentUrl);
    /** Named paginators must restart before the server increases the export batch size. */
    url.searchParams.set(pageName, '1');
    url.searchParams.set('_table_all', '1');
    url.hash = '';
    return url;
}
export function columnType(name) {
    if (exportColumnType(name) === 'date') return 'date';
    if (/^(premio|importo|totale|righe$|gruppi interessati$|quantità|costo|valore economico)/i.test(name)) return 'number';
    return 'text';
}
const valueOf = (cell, type) => {
    if (type === 'date') {
        let m = text(cell).match(/^(\d{4})-(\d{2})-(\d{2})(?:$|\s)/);
        if (!m) { const it = text(cell).match(/^(\d{2})\/(\d{2})\/(\d{4})(?:$|\s)/); if (it) m = [it[0], it[3], it[2], it[1]]; }
        if (!m) return NaN;
        const date = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
        return date.getUTCFullYear() === +m[1] && date.getUTCMonth() === +m[2]-1 && date.getUTCDate() === +m[3] ? +date : NaN;
    }
    if (type === 'number') {
        let s = text(cell).replace(/\s|€/g, '');
        if (s.includes(',')) s = s.replaceAll('.', '').replace(',', '.');
        else if (/^-?\d{1,3}(?:\.\d{3})+$/.test(s)) s = s.replaceAll('.', '');
        return /^-?\d+(?:\.\d+)?$/.test(s) ? Number(s) : NaN;
    }
    return fold(cell);
};

export function matchesFilter(cells, rule) {
    const raw = cells[rule.column] ?? '';
    if (rule.operator === 'empty') return text(raw) === '';
    if (rule.operator === 'filled') return text(raw) !== '';
    const a = valueOf(raw, rule.type), b = valueOf(rule.value, rule.type);
    if (typeof a === 'number' && (!Number.isFinite(a) || !Number.isFinite(b))) return false;
    switch (rule.operator) {
        case 'contains': return fold(raw).includes(fold(rule.value));
        case 'not_contains': return !fold(raw).includes(fold(rule.value));
        case 'starts': return fold(raw).startsWith(fold(rule.value));
        case 'eq': return a === b;
        case 'ne': return a !== b;
        case 'gt': return a > b;
        case 'gte': return a >= b;
        case 'lt': return a < b;
        case 'lte': return a <= b;
        case 'between': return a >= b && a <= valueOf(rule.to, rule.type);
        default: return false;
    }
}

function initialize(table, index) {
    if (states.has(table)) return;
    const headers = [...table.querySelectorAll('thead tr:first-child th')];
    const columns = headers.map((h, i) => ({ index: i, name: text(h.textContent), exportType: h.dataset.exportType || exportColumnType(text(h.textContent)) })).filter(c => c.name && !/^(azioni|seleziona)$/i.test(c.name));
    if (!columns.length || !table.tBodies.length) return;
    const id = `table-tools-${++serial}`;
    const body = table.tBodies[0];
    const panel = table.closest('.panel') || table.parentElement;
    const pager = panel.querySelector('nav[role="navigation"]');
    const serverSummary = panel.querySelector('[data-table-page-summary]');
    const paginated = !!pager?.querySelector('a[href]');
    const state = { rows: null, applied: [], page: 1, busy: false, original: [...body.children], index, mode: 'all' };
    states.set(table, state);
    const title = text(table.closest('[role="region"]')?.getAttribute('aria-label') || panel.querySelector('.panel-title')?.textContent || document.querySelector('h1, [role="heading"][aria-level="1"]')?.textContent || document.title);
    const toolbar = element('div', { class: 'table-tools', 'data-table-tools': id });
    const actions = element('div', { class: 'table-tools-actions' });
    const filters = element('form', { id, class: 'table-filter-panel', hidden: '', 'aria-label': `Filtri: ${title}` });
    const rules = element('div', { class: 'table-filter-rules' });
    const mode = element('div', { class: 'table-filter-combine', role: 'group', 'aria-label': 'Come combinare i filtri' });
    let combination = 'all';
    for (const [value, label] of [['all', 'Tutti i criteri'], ['any', 'Almeno un criterio']]) {
        const choice = button(label, () => { combination = value; [...mode.querySelectorAll('button')].forEach(b => b.setAttribute('aria-pressed', String(b === choice))); });
        choice.setAttribute('aria-pressed', String(value === combination)); mode.append(choice);
    }
    const info = element('p', { class: 'u-text-meta table-export-status', role: 'status', 'aria-live': 'polite' });
    const navigation = element('div', { class: 'table-filter-pagination', hidden: '' });
    const pageLabel = element('span', { class: 'u-text-meta' });
    const toggle = button('Filtra tabella', () => { filters.hidden = !filters.hidden; toggle.setAttribute('aria-expanded', String(!filters.hidden)); if (!filters.hidden) picker.querySelector('button')?.focus(); });
    toggle.setAttribute('aria-controls', id); toggle.setAttribute('aria-expanded', 'false');
    const excel = button('Esporta Excel', () => download('xlsx'));
    const pdf = button('Esporta PDF', () => download('pdf'));
    actions.append(toggle, excel, pdf);
    const instructions = element('p', { class: 'table-filter-help' }, 'Scegli cosa filtrare, inserisci il valore e premi Applica filtri. Puoi aggiungere più criteri.');
    const picker = element('div', { class: 'table-filter-picker', role: 'group', 'aria-label': 'Scegli cosa filtrare' });
    columns.forEach(column => picker.append(button(column.name, () => { addRule(column); rules.lastElementChild.querySelector('input')?.focus(); })));
    const pickerTitle = element('p', { class: 'table-filter-label' }, 'Filtra per');
    const combinationLabel = element('span', { class: 'table-filter-label' }, 'Mostra le righe che rispettano');
    mode.prepend(combinationLabel);
    const filterActions = element('div', { class: 'table-tools-actions' });
    const apply = element('button', { type: 'submit', class: 'btn btn-p btn-sm' }, 'Applica filtri');
    filterActions.append(apply, button('Azzera filtri', reset), button('Chiudi filtri', () => { filters.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }));
    filters.append(instructions, pickerTitle, picker, rules, mode, filterActions);
    toolbar.append(actions, filters, info, navigation);
    let anchor = table;
    if (table.parentElement?.classList.contains('client-table-scroll') || table.parentElement?.classList.contains('attachments-table-wrap')) anchor = table.parentElement;
    anchor.before(toolbar);
    const previous = button('Precedente', () => { state.page--; render(); });
    const next = button('Successiva', () => { state.page++; render(); });
    navigation.append(previous, pageLabel, next);

    function addRule(column) {
        const type = columnType(column.name);
        let operator = type === 'text' ? 'contains' : 'eq';
        const row = element('fieldset', { class: 'table-filter-rule' });
        const legend = element('legend', {}, column.name);
        const choices = type === 'text'
            ? [['contains','Contiene'], ['eq','È uguale a'], ['not_contains','Non contiene'], ['starts','Inizia con'], ['empty','È vuoto'], ['filled','È compilato']]
            : type === 'date'
                ? [['eq','Il giorno'], ['gte','Dal giorno'], ['lte','Fino al giorno'], ['between','Tra due date'], ['empty','Data mancante']]
                : [['eq','Uguale a'], ['gte','Almeno'], ['lte','Al massimo'], ['between','Tra due valori'], ['empty','Valore mancante']];
        const operators = element('div', { class: 'table-filter-operators', role: 'group', 'aria-label': `Condizione per ${column.name}` });
        const fields = element('div', { class: 'table-filter-values' });
        const inputId = `${id}-value-${++serial}`;
        const value = element('input', { id: inputId, class: 'form-in', type: type === 'date' ? 'date' : 'text', 'aria-label': `Valore per ${column.name}`, maxlength: '500', placeholder: type === 'number' ? 'Inserisci un importo o un numero' : `Scrivi ${column.name.toLocaleLowerCase('it')}` });
        if (type === 'number') value.inputMode = 'decimal';
        const valueLabel = element('label', { for: inputId, class: 'table-filter-value' });
        const valueTitle = element('span', { class: 'table-filter-label' }, 'Valore'); valueLabel.append(valueTitle, value);
        const to = element('input', { id: `${inputId}-to`, class: 'form-in', type: type === 'date' ? 'date' : 'text', 'aria-label': `Valore finale per ${column.name}`, maxlength: '500' });
        if (type === 'number') to.inputMode = 'decimal';
        const toLabel = element('label', { for: `${inputId}-to`, class: 'table-filter-value', hidden: '' });
        toLabel.append(element('span', { class: 'table-filter-label' }, 'A'), to);
        const update = () => {
            valueLabel.hidden = ['empty','filled'].includes(operator); toLabel.hidden = operator !== 'between';
            value.disabled = valueLabel.hidden; to.disabled = toLabel.hidden;
            value.required = !valueLabel.hidden; to.required = !toLabel.hidden;
            valueTitle.textContent = operator === 'between' ? 'Da' : type === 'date' ? 'Data' : 'Valore';
        };
        choices.forEach(([key,label]) => {
            const choice = button(label, () => { operator = key; [...operators.children].forEach(b => b.setAttribute('aria-pressed', String(b === choice))); update(); });
            choice.setAttribute('aria-pressed', String(operator === key)); operators.append(choice);
        });
        const remove = button('Rimuovi filtro', () => { row.remove(); refreshEditor(); picker.querySelector('button')?.focus(); });
        remove.setAttribute('aria-label', `Rimuovi filtro ${column.name}`);
        fields.append(valueLabel, toLabel, remove);
        row.append(legend, operators, fields);
        row.read = () => ({ column: column.index, type, operator, value: value.disabled ? '' : value.value.trim(), to: to.disabled ? '' : to.value.trim() });
        rules.append(row); update(); refreshEditor();
    }
    function refreshEditor() { mode.hidden = rules.children.length < 2; apply.disabled = !rules.children.length; }
    refreshEditor();
    function rowsFrom(sourceTable) {
        return [...sourceTable.tBodies[0].rows].filter(r => r.cells.length === headers.length && !r.querySelector('td[colspan]'))
            .map(node => ({ node, cells: [...node.cells].map(c => {
                const clone = c.cloneNode(true);
                clone.querySelectorAll('button, form, [role="dialog"], script, style, summary').forEach(n => n.remove());
                /** Block boundaries must survive extraction, including details that are collapsed on screen. */
                clone.querySelectorAll('div, p, br, li').forEach(n => { n.before(' '); n.after(' '); });
                return text(clone.textContent);
            }) }));
    }
    async function load() {
        if (!paginated) { state.rows = rowsFrom(table); return; }
        const url = tableExportUrl(location.href, pager.dataset.pageName || 'page');
        const rows = []; const seen = new Set();
        let pageUrl = url.href;
        while (pageUrl) {
            if (seen.has(pageUrl) || seen.size >= 100) throw new Error('Elenco troppo esteso: restringi la ricerca prima di esportare.');
            seen.add(pageUrl);
            const response = await fetch(pageUrl, { headers: { 'X-Table-Export': '1' }, credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok || response.redirected) throw new Error('Impossibile caricare tutte le righe. Verifica la sessione e riprova.');
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const remote = eligible(doc)[state.index];
            if (!remote || text(remote.tHead?.textContent) !== text(table.tHead?.textContent)) throw new Error('La tabella è cambiata: ricarica la pagina.');
            rows.push(...rowsFrom(remote));
            const nextLink = (remote.closest('.panel') || remote.parentElement).querySelector('nav[role="navigation"] a[rel="next"]');
            pageUrl = nextLink ? new URL(nextLink.getAttribute('href'), url).href : null;
            if (pageUrl && new URL(pageUrl).origin !== location.origin) throw new Error('Collegamento di paginazione non valido.');
            if (pageUrl) { const nextUrl = new URL(pageUrl); nextUrl.searchParams.set('_table_all', '1'); pageUrl = nextUrl.href; }
            info.textContent = `Caricamento: ${rows.length} righe…`;
        }
        state.rows = rows;
    }
    function matching() {
        return (state.rows || rowsFrom(table)).filter(r => !state.applied.length || (state.mode === 'any' ? state.applied.some(f => matchesFilter(r.cells,f)) : state.applied.every(f => matchesFilter(r.cells,f))));
    }
    function render() {
        const found = matching();
        if (paginated) {
            if (serverSummary) serverSummary.hidden = true;
            const pages = Math.max(1, Math.ceil(found.length/50)); state.page = Math.min(state.page, pages);
            const nodes = found.slice((state.page-1)*50, state.page*50).map(r => document.importNode(r.node, true));
            window.Alpine?.destroyTree(body); body.replaceChildren(...nodes); window.Alpine?.initTree(body);
            pager.hidden = true; navigation.hidden = pages <= 1;
            pageLabel.textContent = `Pagina ${state.page} di ${pages}`; previous.disabled = state.page <= 1; next.disabled = state.page >= pages;
        } else {
            const matches = new Set(found.map(r => r.node));
            for (const row of state.rows) row.node.hidden = !matches.has(row.node);
        }
        info.textContent = `${found.length} ${found.length === 1 ? 'risultato' : 'risultati'} su ${state.rows.length}. Excel e PDF includono tutti i risultati filtrati.`;
        table.dispatchEvent(new CustomEvent('table:rendered', { bubbles: true }));
    }
    function reset() {
        state.applied = []; state.rows = null; state.page = 1;
        if (serverSummary) serverSummary.hidden = false;
        if (paginated) { window.Alpine?.destroyTree(body); body.replaceChildren(...state.original); window.Alpine?.initTree(body); pager.hidden = false; }
        for (const row of body.rows) row.hidden = false;
        navigation.hidden = true; info.textContent = ''; rules.replaceChildren(); refreshEditor();
        toggle.textContent = 'Filtra tabella';
        combination = 'all'; [...mode.querySelectorAll('button')].forEach((b,i) => b.setAttribute('aria-pressed', String(i === 0)));
        table.dispatchEvent(new CustomEvent('table:rendered', { bubbles: true }));
    }
    async function busy(action) {
        if (state.busy) return;
        state.busy = true; [excel, pdf, ...filters.querySelectorAll('button')].forEach(b => b.disabled = true);
        try { await action(); } catch (error) { info.textContent = error.message || 'Operazione non completata. Riprova.'; }
        finally { state.busy = false; [excel, pdf, ...filters.querySelectorAll('button')].forEach(b => b.disabled = false); refreshEditor(); }
    }
    filters.addEventListener('submit', event => {
        event.preventDefault();
        busy(async () => {
            const active = [...rules.children].map(row => row.read());
            if (!active.length) { reset(); return; }
            for (const r of active) if (!['empty','filled'].includes(r.operator) && r.type !== 'text' && (!Number.isFinite(valueOf(r.value,r.type)) || (r.operator === 'between' && (!Number.isFinite(valueOf(r.to,r.type)) || valueOf(r.to,r.type)<valueOf(r.value,r.type))))) throw new Error('Controlla valori e intervalli dei filtri.');
            await load(); state.applied = active; state.mode = combination; state.page = 1; render();
            toggle.textContent = `Filtri applicati (${active.length})`;
        });
    });
    async function download(format) {
        await busy(async () => {
            await load(); const rows = matching().map(r => columns.map(c => r.cells[c.index]));
            info.textContent = `Preparazione ${format === 'xlsx' ? 'Excel' : 'PDF'}: ${rows.length} ${rows.length === 1 ? 'riga' : 'righe'}…`;
            const operators = { contains:'contiene', not_contains:'non contiene', starts:'inizia con', eq:'uguale a', ne:'diverso da', gt:'maggiore / dopo', gte:'maggiore o uguale', lt:'minore / prima', lte:'minore o uguale', between:'compreso tra', empty:'vuoto', filled:'compilato' };
            const filtersText = state.applied.map(r => `${columns.find(c=>c.index===r.column)?.name}: ${operators[r.operator]} ${r.value}${r.to ? ' e '+r.to : ''}`).join(state.mode === 'any' ? ' oppure ' : '; ');
            const searchLabels = { search:'Ricerca', client_type:'Tipo cliente', status:'Stato', category:'Ramo', subcategory:'Sottoramo', period:'Periodo', date:'Giornata', month:'Mese', date_basis:'Data di riferimento', agency:'Agenzia', payment_frequency:'Frazionamento', from:'Dal', to:'Al', supplier_company_id:'Compagnia', company:'Compagnia', responsible:'Responsabile cliente', branch:'Ramo principale', review:'Solo da verificare', document_view:'Versioni documenti', unassigned:'Compagnia da assegnare', import_review:'Dati importati da verificare', kind:'Anagrafica', result:'Esito' };
            const searchFilters = [...new URL(location.href).searchParams].filter(([key,value]) => searchLabels[key] && value).map(([key,value]) => {
                const field = [...document.querySelectorAll('form[method="GET"] [name]')].find(el => el.name === key);
                const label = field?.dataset.exportLabel || text(field?.labels?.[0]?.textContent) || searchLabels[key];
                const display = field?.dataset.exportValue || field?.selectedOptions?.[0]?.textContent || (key === 'document_view' ? ({ current:'Documenti correnti', history:'Tutte le versioni' }[value] || value) : value);
                return `${label}: ${display}`;
            }).join('; ');
            const { exportTable } = await import('./table-export.js');
            await exportTable({ format, title, headers: columns.map(c => c.name), columnTypes: columns.map(c => c.exportType), rows, filters: filtersText, searchFilters });
            info.textContent = `Esportazione completata: ${rows.length} ${rows.length === 1 ? 'riga' : 'righe'}.`;
        });
    }
}

function init() { eligible(document).forEach(initialize); }
document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
document.addEventListener('livewire:initialized', () => { window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(init))); });
