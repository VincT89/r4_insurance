import { exportColumnType } from './table-export-values.js';
import { collectionExportFooters } from './collection-totals.js';
import { allowanceExportFooters } from './allowance-totals.js';
import { initializeListDetails, initializeListFilters } from './list-details.js';

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
function labelTableRows(table) {
    const head = table.tHead;
    if (head?.rows.length !== 1 || !table.tBodies.length) return;
    const headers = [...head.rows[0].cells];
    if (headers.some(cell => cell.colSpan !== 1)) return;
    headers.forEach(header => { header.dataset.columnKind = exportColumnType(text(header.textContent)); });
    if (!table.parentElement.classList.contains('client-table-scroll')) {
        const title = text(table.closest('.panel')?.querySelector('.panel-title')?.textContent || document.querySelector('[role="heading"][aria-level="1"], h1')?.textContent || document.title);
        const wrapper = element('div', {class:'client-table-scroll',role:'region','aria-label':title,tabindex:'0'});
        table.before(wrapper);
        wrapper.append(table);
    }
    for (const row of table.tBodies[0].rows) {
        if (row.cells.length !== headers.length || [...row.cells].some(cell => cell.colSpan !== 1)) continue;
        [...row.cells].forEach((cell, index) => {
            if (!cell.hasAttribute('data-label')) cell.dataset.label = text(headers[index].textContent);
            cell.dataset.columnKind = headers[index].dataset.columnKind;
        });
    }
    table.setAttribute('data-responsive-table', '');
    table.toggleAttribute('data-empty-table', ![...table.tBodies[0].rows].some(row => row.cells.length === headers.length && ![...row.cells].some(cell => cell.colSpan !== 1)));
}
export function tableExportUrl(currentUrl, pageName = 'page', appliedFilters = {}) {
    const url = new URL(currentUrl);
    /** Freeze server defaults, such as today's payment dates, for every export page. */
    for (const [key, value] of Object.entries(appliedFilters)) if (value !== null) url.searchParams.set(key, String(value));
    /** Named paginators must restart before the server increases the export batch size. */
    url.searchParams.set(pageName, '1');
    url.searchParams.set('_table_all', '1');
    url.hash = '';
    return url;
}
export function columnType(name) {
    const type = exportColumnType(name);
    if (type === 'date') return 'date';
    if (['currency', 'integer', 'percent', 'number'].includes(type)) return 'number';
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
        let s = text(cell).replace(/\s|€|%/g, '');
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

export function restoreTableFilters(raw, columns) {
    if (!raw) return { mode: 'all', rules: [] };
    if (raw.length > 16000) throw new Error('Troppi filtri: azzera i filtri e riprova.');
    let data;
    try { data = JSON.parse(raw); } catch { throw new Error('Filtri non validi: azzera i filtri e riprova.'); }
    if (!data || !['all','any'].includes(data.mode) || !Array.isArray(data.rules) || data.rules.length > 20) throw new Error('Filtri non validi.');
    const rules = data.rules.map(rule => {
        const column = columns.find(column => column.name === rule?.column);
        const type = column && columnType(column.name);
        const allowed = type === 'text' ? ['contains','eq','not_contains','starts','empty','filled'] : ['eq','gte','lte','between','empty'];
        if (!column || !allowed.includes(rule.operator) || typeof rule.value !== 'string' || typeof rule.to !== 'string' || rule.value.length > 500 || rule.to.length > 500) throw new Error('Un filtro non è più disponibile: azzera i filtri e riprova.');
        return { column: column.index, type, operator: rule.operator, value: rule.value, to: rule.to };
    });
    return { mode: data.mode, rules };
}

export function storeTableFilters(rules, mode, columns) {
    return rules.length ? JSON.stringify({ mode, rules: rules.map(rule => ({ column: columns.find(column=>column.index===rule.column).name, operator: rule.operator, value: rule.value, to: rule.to })) }) : '';
}

function initialize(table, index) {
    if (states.has(table) || table.dataset.tableTools==='none') return;
    const headers = [...table.querySelectorAll('thead tr:first-child th')];
    const columns = headers.map((h, i) => ({ index: i, name: text(h.textContent), exportType: h.dataset.exportType || exportColumnType(text(h.textContent)) })).filter(c => c.name && !/^(azioni|seleziona)$/i.test(c.name));
    if (!columns.length || !table.tBodies.length) return;
    const id = `table-tools-${++serial}`;
    const body = table.tBodies[0];
    const panel = table.closest('.panel') || table.parentElement;
    const pager = panel.querySelector('nav[role="navigation"]');
    const serverSummary = panel.querySelector('[data-table-page-summary]');
    const paginated = !!pager?.querySelector('a[href]');
    /** Le tabelle vuote non hanno azioni utili; restano disponibili i filtri integrati e gli elenchi paginati. */
    if (!table.dataset.filterForm && !paginated && ![...body.rows].some(row => row.cells.length === headers.length && !row.querySelector('td[colspan]'))) return;
    const state = { rows: null, applied: [], page: 1, busy: false, original: [...body.children], index, mode: 'all', invalidFilters:false };
    states.set(table, state);
    const accounting = table.matches('[data-collection-table]');
    const exportOnly = table.hasAttribute('data-export-only');
    const accountingWorkspace = accounting ? table.closest('[data-collection-workspace]') : null;
    const title = text(table.closest('[role="region"]')?.getAttribute('aria-label') || panel.querySelector('.panel-title')?.textContent || document.querySelector('h1, [role="heading"][aria-level="1"]')?.textContent || document.title);
    const toolbar = element('div', { class: 'table-tools', 'data-table-tools': id });
    const filterForm = table.dataset.filterForm ? document.getElementById(table.dataset.filterForm) : null;
    const actions = element('div', { class: 'table-tools-actions' });
    const filters = element(filterForm ? 'div' : 'form', { id, class: 'table-filter-panel', 'aria-label': `Filtri: ${title}` });
    filters.hidden = !filterForm;
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
    const toggle = button('Filtra tabella', () => { filters.hidden = !filters.hidden; toggle.setAttribute('aria-expanded', String(!filters.hidden)); if (!filters.hidden) picker.querySelector('select')?.focus(); });
    toggle.setAttribute('aria-controls', id); toggle.setAttribute('aria-expanded', 'false');
    const excel = button('Esporta Excel', () => download('xlsx'));
    const pdf = button(accounting ? 'Stampa / PDF di tutti' : 'Esporta PDF', () => download('pdf'));
    if (!filterForm && !exportOnly) actions.append(toggle);
    actions.append(excel, pdf);
    const instructions = element('p', { class: 'table-filter-help' }, 'Puoi aggiungere criteri specifici e applicarli insieme agli altri filtri.');
    const picker = element('div', { class: 'table-filter-picker', role: 'group', 'aria-label': 'Scegli cosa filtrare' });
    const fieldPicker = element('select', { id: `${id}-column`, class: 'form-sel' });
    fieldPicker.append(element('option', { value: '' }, 'Scegli un campo'));
    columns.forEach(column => fieldPicker.append(element('option', { value: String(column.index) }, column.name)));
    const add = button('Aggiungi filtro', () => {
        const column = columns.find(column => String(column.index) === fieldPicker.value);
        if (!column) return;
        addRule(column); rules.lastElementChild.querySelector('input')?.focus(); fieldPicker.value = ''; refreshEditor();
    });
    fieldPicker.addEventListener('change', refreshEditor);
    picker.append(fieldPicker, add);
    const pickerTitle = element('label', { class: 'table-filter-label', for: fieldPicker.id }, 'Filtra per');
    const combinationLabel = element('span', { class: 'table-filter-label' }, 'Mostra le righe che rispettano');
    mode.prepend(combinationLabel);
    const filterActions = element('div', { class: 'table-tools-actions' });
    const apply = element('button', { type: 'submit', class: 'btn btn-p btn-sm' }, 'Applica filtri');
    filterActions.append(apply, button('Azzera filtri', reset), button('Chiudi filtri', () => { filters.hidden = true; toggle.setAttribute('aria-expanded', 'false'); toggle.focus(); }));
    filters.append(instructions, pickerTitle, picker, rules, mode);
    if (!filterForm) filters.append(filterActions);
    toolbar.append(actions, info, navigation);
    if (filterForm) (filterForm.querySelector('[data-column-filters]') || filterForm).append(filters);
    else if (!exportOnly) actions.after(filters);
    let anchor = table;
    if (table.parentElement?.classList.contains('client-table-scroll') || table.parentElement?.classList.contains('attachments-table-wrap')) anchor = table.parentElement;
    const actionsContainer = accountingWorkspace?.querySelector('[data-collection-actions]') || panel.querySelector('[data-policy-export-actions]') || (exportOnly && panel.querySelector('[data-export-controls]'));
    if (actionsContainer) actionsContainer.append(toolbar); else anchor.before(toolbar);
    accountingWorkspace?.addEventListener('click',event=> {
        const control = event.target.closest('[data-collection-export]');
        if (control && accountingWorkspace.contains(control)) download(control.dataset.collectionExport,control.dataset.collectionGroup ?? null);
    });
    const previous = button('Precedente', () => { state.page--; render(); });
    const next = button('Successiva', () => { state.page++; render(); });
    navigation.append(previous, pageLabel, next);

    function addRule(column, initial = null) {
        const type = columnType(column.name);
        let operator = initial?.operator || (type === 'text' ? 'contains' : 'eq');
        const row = element('fieldset', { class: 'table-filter-rule' });
        const legend = element('legend', {}, column.name);
        const choices = type === 'text'
            ? [['contains','Contiene'], ['eq','È uguale a'], ['not_contains','Non contiene'], ['starts','Inizia con'], ['empty','È vuoto'], ['filled','È compilato']]
            : type === 'date'
                ? [['eq','Il giorno'], ['gte','Dal giorno'], ['lte','Fino al giorno'], ['between','Tra due date'], ['empty','Data mancante']]
                : [['eq','Uguale a'], ['gte','Almeno'], ['lte','Al massimo'], ['between','Tra due valori'], ['empty','Valore mancante']];
        const fields = element('div', { class: 'table-filter-values' });
        const inputId = `${id}-value-${++serial}`;
        const operators = element('select', { id: `${inputId}-operator`, class: 'form-sel', 'aria-label': `Condizione per ${column.name}` });
        choices.forEach(([key, label]) => operators.append(element('option', { value: key }, label)));
        operators.value = operator;
        const operatorLabel = element('label', { for: operators.id, class: 'table-filter-value' });
        operatorLabel.append(element('span', { class: 'table-filter-label' }, 'Condizione'), operators);
        const value = element('input', { id: inputId, class: 'form-in', type: type === 'date' ? 'date' : 'text', 'aria-label': `Valore per ${column.name}`, maxlength: '500', placeholder: type === 'number' ? 'Inserisci un importo o un numero' : `Scrivi ${column.name.toLocaleLowerCase('it')}` });
        if (type === 'number') value.inputMode = 'decimal';
        const valueLabel = element('label', { for: inputId, class: 'table-filter-value' });
        const valueTitle = element('span', { class: 'table-filter-label' }, 'Valore'); valueLabel.append(valueTitle, value);
        const to = element('input', { id: `${inputId}-to`, class: 'form-in', type: type === 'date' ? 'date' : 'text', 'aria-label': `Valore finale per ${column.name}`, maxlength: '500' });
        if (type === 'number') to.inputMode = 'decimal';
        value.value = initial?.value || ''; to.value = initial?.to || '';
        const toLabel = element('label', { for: `${inputId}-to`, class: 'table-filter-value', hidden: '' });
        toLabel.append(element('span', { class: 'table-filter-label' }, 'A'), to);
        const update = () => {
            valueLabel.hidden = ['empty','filled'].includes(operator); toLabel.hidden = operator !== 'between';
            value.disabled = valueLabel.hidden; to.disabled = toLabel.hidden;
            value.required = !valueLabel.hidden; to.required = !toLabel.hidden;
            valueTitle.textContent = operator === 'between' ? 'Da' : type === 'date' ? 'Data' : 'Valore';
        };
        operators.addEventListener('change', () => { operator = operators.value; update(); });
        const remove = button('Rimuovi filtro', () => { row.remove(); refreshEditor(); fieldPicker.focus(); });
        remove.setAttribute('aria-label', `Rimuovi filtro ${column.name}`);
        fields.append(operatorLabel, valueLabel, toLabel, remove);
        row.append(legend, fields);
        row.read = () => ({ column: column.index, type, operator, value: value.disabled ? '' : value.value.trim(), to: to.disabled ? '' : to.value.trim() });
        rules.append(row); update(); refreshEditor();
    }
    function refreshEditor() { mode.hidden = rules.children.length < 2; apply.disabled = !rules.children.length; add.disabled = !fieldPicker.value || rules.children.length >= 20; }
    refreshEditor();
    function rowsFrom(sourceTable) {
        return [...sourceTable.tBodies[0].rows].filter(r => r.cells.length === headers.length && !r.querySelector('td[colspan]'))
            .map(node => ({ node, cells: [...node.cells].map(c => {
                const clone = c.cloneNode(true);
                clone.querySelectorAll('button, form, [role="dialog"], script, style, summary, [data-table-export-ignore]').forEach(n => n.remove());
                /** Block boundaries must survive extraction, including details that are collapsed on screen. */
                clone.querySelectorAll('div, p, br, li').forEach(n => { n.before(' '); n.after(' '); });
                return text(clone.textContent);
            }) }));
    }
    async function load() {
        if (!paginated) { state.rows = rowsFrom(table); return; }
        const url = tableExportUrl(location.href, pager.dataset.pageName || 'page', JSON.parse(table.dataset.tableQuery || '{}'));
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
        table.dispatchEvent(new CustomEvent('table:filtered', { bubbles: true, detail: { rows: found.map(row => row.node.dataset) } }));
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
        table.dispatchEvent(new CustomEvent('table:filtered', { bubbles: true, detail: { rows: null } }));
    }
    async function busy(action) {
        if (state.busy || state.invalidFilters) return;
        const controls = () => [excel,pdf,...filters.querySelectorAll('button'),...(accountingWorkspace?.querySelectorAll('[data-collection-export]') || [])];
        state.busy = true; controls().forEach(b => b.disabled = true);
        try { await action(); } catch (error) { info.textContent = error.message || 'Operazione non completata. Riprova.'; }
        finally { state.busy = false; controls().forEach(b => b.disabled = false); refreshEditor(); }
    }
    function readRules() {
        const active = [...rules.children].map(row => row.read());
        if (active.length > 20) throw new Error('Puoi combinare al massimo 20 criteri.');
        for (const r of active) if (!['empty','filled'].includes(r.operator) && r.type !== 'text' && (!Number.isFinite(valueOf(r.value,r.type)) || (r.operator === 'between' && (!Number.isFinite(valueOf(r.to,r.type)) || valueOf(r.to,r.type)<valueOf(r.value,r.type))))) throw new Error('Controlla valori e intervalli dei filtri.');
        return active;
    }
    if (filterForm) {
        const saved = element('input', { type:'hidden', name:'_table_filters', disabled:'' });
        filterForm.append(saved);
        filterForm.addEventListener('submit', event => {
            try { saved.value = storeTableFilters(readRules(), combination, columns); saved.disabled = !saved.value; }
            catch (error) { event.preventDefault(); info.textContent = error.message; info.scrollIntoView({block:'nearest'}); }
        });
        (filterForm.closest('#content-area') || document).querySelectorAll('a[data-keep-table-filters]').forEach(link => link.addEventListener('click', () => {
            const url = new URL(link.href), value = storeTableFilters(state.applied, state.mode, columns);
            if (value) url.searchParams.set('_table_filters', value); else url.searchParams.delete('_table_filters');
            link.href = url.href;
        }));
        try {
            const restored = restoreTableFilters(new URL(location.href).searchParams.get('_table_filters'), columns);
            combination = restored.mode; state.mode = restored.mode;
            [...mode.querySelectorAll('button')].forEach((button,index) => button.setAttribute('aria-pressed', String(index === (combination==='all' ? 0 : 1))));
            for (const rule of restored.rules) addRule(columns.find(column=>column.index===rule.column), rule);
            state.applied = readRules();
            if (state.applied.length) {
                const details = filters.closest('details');
                if (details && !filterForm.hasAttribute('data-compact-filter-results')) details.open = true;
                info.textContent = 'Applicazione dei filtri a tutti i risultati…';
                busy(async () => { await load(); render(); });
            }
        } catch (error) {
            info.textContent = error.message;
            /** An invalid saved accounting filter must never produce an apparently filtered financial report. */
            if (accounting || table.matches('[data-allowance-table]')) {state.invalidFilters=true;[excel,pdf,...(accountingWorkspace?.querySelectorAll('[data-collection-export]') || [])].forEach(button=>button.disabled=true);}
        }
    } else filters.addEventListener('submit', event => {
        event.preventDefault();
        busy(async () => {
            const active = readRules();
            if (!active.length) { reset(); return; }
            await load(); state.applied = active; state.mode = combination; state.page = 1; render();
            toggle.textContent = `Filtri applicati (${active.length})`;
        });
    });
    async function download(format, groupKey = null) {
        await busy(async () => {
            await load(); const matched = matching();
            const rows = matched.map(r => columns.map(c => r.cells[c.index]));
            const footers = table.matches('[data-collection-table]') ? collectionExportFooters(matched.map(row => row.node.dataset), columns.map(column=>column.name))
                : (table.matches('[data-allowance-table]') ? allowanceExportFooters(matched.map(row => row.node.dataset), columns.map(column=>column.name)) : []);
            info.textContent = `Preparazione ${format === 'xlsx' ? 'Excel' : 'PDF'}: ${rows.length} ${rows.length === 1 ? 'riga' : 'righe'}…`;
            const operators = { contains:'contiene', not_contains:'non contiene', starts:'inizia con', eq:'uguale a', ne:'diverso da', gt:'maggiore / dopo', gte:'maggiore o uguale', lt:'minore / prima', lte:'minore o uguale', between:'compreso tra', empty:'vuoto', filled:'compilato' };
            const filtersText = state.applied.map(r => `${columns.find(c=>c.index===r.column)?.name}: ${operators[r.operator]} ${r.value}${r.to ? ' e '+r.to : ''}`).join(state.mode === 'any' ? ' oppure ' : '; ');
            const searchLabels = { search:'Ricerca', client:'Cliente', policy:'Polizza', client_type:'Tipo cliente', status:'Stato', category:'Ramo', subcategory:'Sottoramo', period:'Periodo', operation:'Operazione', date:'Giornata', month:'Mese', date_basis:'Data di riferimento', agency:'Agenzia', payment_frequency:'Frazionamento', from:'Dal', to:'Al', titles_from:'Scadenza titoli dal', titles_to:'Scadenza titoli al', payment_method:'Modalità di pagamento', supplier_company_id:'Compagnia', company:'Compagnia', responsible:'Responsabile cliente', branch:'Ramo principale', subbranch:'Sottoramo', basis:'Base di calcolo', on:'Valide alla data', state:'Stato', review:'Solo da verificare', document_view:'Versioni documenti', unassigned:'Compagnia da assegnare', import_review:'Dati importati da verificare', kind:'Anagrafica', result:'Esito',direction:'Movimento',recipient:'Gestito da',consultant:'Consulente',document_type:'Tipo documento',plate:'Targa',cu:'Classe CU',merit_class_cu:'Classe CU',company_id:'Compagnia',agency_id:'Agenzia',expires_from:'Scadenza dal',expires_to:'Scadenza al' };
            Object.assign(searchLabels, {borne_by:'Chi sostiene l’abbuono',bearer_agency:'Agenzia che sostiene l’abbuono'});
            const appliedUrl = tableExportUrl(location.href, pager?.dataset.pageName || 'page', JSON.parse(table.dataset.tableQuery || '{}'));
            const searchFilters = [...appliedUrl.searchParams].filter(([key,value]) => searchLabels[key] && value).map(([key,value]) => {
                const field = [...document.querySelectorAll('form[method="GET"] [name]')].find(el => el.name === key);
                if (field?.disabled && !accounting) return null;
                const label = field?.dataset.exportLabel || text(field?.labels?.[0]?.textContent) || searchLabels[key];
                const display = field?.dataset.exportValue || [...(field?.options || [])].find(option => option.value === value)?.textContent || (key === 'document_view' ? ({ current:'Documenti correnti', history:'Tutte le versioni' }[value] || value) : value);
                const readable = field?.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(display) ? display.split('-').reverse().join('/') : display;
                return `${label}: ${readable}`;
            }).filter(Boolean).join('; ');
            let exported = rows.length;
            if (accounting) {
                const [{collectionReport},{exportCollectionReport}] = await Promise.all([import('./collection-report.js'),import('./collection-export.js')]);
                const report = collectionReport(matched.map(row=>({data:row.node.dataset,cells:columns.map(c=>row.cells[c.index])})),columns.map(c=>c.name),groupKey);
                await exportCollectionReport({format,report,filters:filtersText,searchFilters}); exported=report.rows.length;
            } else {
                const { exportTable } = await import('./table-export.js');
                await exportTable({ format, title, headers: columns.map(c => c.name), columnTypes: columns.map(c => c.exportType), rows, footers, filters: filtersText, searchFilters });
            }
            info.textContent = `Esportazione completata: ${exported} ${exported === 1 ? 'riga' : 'righe'}.`;
        });
    }
}

function init() {
    document.querySelectorAll('form[data-list-filters]').forEach(initializeListFilters);
    eligible(document).forEach((table, index) => { labelTableRows(table); initializeListDetails(table); initialize(table, index); });
}
document.addEventListener('table:rendered', event => {
    if (event.target.matches?.('table.t-table')) labelTableRows(event.target);
});
document.addEventListener('DOMContentLoaded', init);
document.addEventListener('livewire:navigated', init);
document.addEventListener('livewire:initialized', () => { window.Livewire.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(init))); });
