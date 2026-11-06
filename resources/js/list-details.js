const initialized = new WeakSet();
const initializedFilters = new WeakSet();
let sequence = 0;

export function initializeListFilters(form) {
    if (initializedFilters.has(form)) return;
    const details = form.querySelector('.search-more');
    const fields = details?.querySelector('.search-more-fields');
    if (!fields) return;
    initializedFilters.add(form);
    const groups = [...form.children].filter(node => node.matches('.form-group, .form-g')).slice(1);
    const positions = groups.map(group => {
        const position = document.createTextNode('');
        group.before(position);
        return position;
    });
    const mobile = window.matchMedia('(max-width: 900px)');
    const arrange = () => {
        if (mobile.matches) {
            fields.prepend(...groups);
            if (groups.some(group => [...group.querySelectorAll('[name]')].some(field => field.value && field.value !== 'all'))) details.open = true;
        } else groups.forEach((group, index) => positions[index].before(group));
    };
    mobile.addEventListener('change', arrange);
    arrange();
}

export function initializeListDetails(table) {
    if (!table.hasAttribute('data-list-details') || initialized.has(table) || !table.tHead || !table.tBodies[0]) return;
    initialized.add(table);
    const headers = [...table.tHead.rows[0].cells];
    const secondary = headers.map((cell, index) => cell.hasAttribute('data-list-detail') ? index : -1).filter(index => index >= 0);
    const visibleColumns = headers.length - secondary.length;
    const primaryColumn = headers.findIndex((cell, index) => !secondary.includes(index));
    const detailsLabel = table.dataset.detailsLabel || 'Altri dati';
    const body = table.tBodies[0];

    function refresh() {
        const footer = table.tFoot?.rows[0];
        if (footer?.cells.length === 1 && footer.cells[0].hasAttribute('colspan')) footer.cells[0].colSpan = visibleColumns;
        body.querySelectorAll('[data-list-detail-row]').forEach(row => row.remove());
        for (const row of [...body.rows]) {
            if (row.cells.length === 1 && row.cells[0].hasAttribute('colspan')) row.cells[0].colSpan = visibleColumns;
            if (row.cells.length !== headers.length || row.querySelector('td[colspan]')) continue;
            [...row.cells].forEach((cell, index) => {
                cell.dataset.label = headers[index].textContent.trim();
                cell.toggleAttribute('data-list-detail', secondary.includes(index));
            });
            if (row.hasAttribute('data-no-list-details')) continue;
            let control = row.querySelector('[data-list-details-toggle]');
            if (!control) {
                control = document.createElement('button');
                control.type = 'button';
                control.className = 'btn btn-g btn-sm list-details-toggle';
                control.setAttribute('data-list-details-toggle', '');
            }
            row.cells[primaryColumn].append(control);
            const name = row.dataset.listLabel || row.cells[primaryColumn].querySelector('a')?.textContent.trim() || headers[primaryColumn].textContent.trim();
            const detailRow = document.createElement('tr');
            detailRow.id = `list-details-${++sequence}`;
            detailRow.setAttribute('data-list-detail-row', '');
            detailRow.hidden = true;
            const cell = detailRow.insertCell();
            /** Le colonne nascoste non occupano spazio nella griglia visiva della tabella. */
            cell.colSpan = visibleColumns;
            row.after(detailRow);
            control.textContent = detailsLabel;
            control.setAttribute('aria-label', `${detailsLabel}: ${name}`);
            control.setAttribute('aria-expanded', 'false');
            control.setAttribute('aria-controls', detailRow.id);
        }
        table.setAttribute('data-list-enhanced', '');
    }

    table.addEventListener('click', event => {
        const control = event.target.closest('[data-list-details-toggle]');
        if (!control || control.closest('table') !== table) return;
        const row = control.closest('tr');
        const detailRow = row.nextElementSibling;
        if (!detailRow?.hasAttribute('data-list-detail-row')) return;
        const open = detailRow.hidden;
        if (open && !detailRow.cells[0].hasChildNodes()) {
            const list = document.createElement('dl');
            list.className = 'list-detail-fields';
            for (const index of secondary) {
                const field = document.createElement('div');
                const label = document.createElement('dt');
                label.textContent = headers[index].textContent.trim();
                const value = document.createElement('dd');
                /** I dati originali restano nelle colonne per ricerca ed esportazioni complete. */
                const copy = row.cells[index].cloneNode(true);
                copy.querySelectorAll('[id]').forEach(node => node.removeAttribute('id'));
                value.append(...copy.childNodes);
                field.append(label, value);
                list.append(field);
            }
            detailRow.cells[0].append(list);
        }
        detailRow.hidden = !open;
        control.setAttribute('aria-expanded', String(open));
        control.textContent = open ? 'Nascondi '+detailsLabel.toLowerCase() : detailsLabel;
        const name = row.dataset.listLabel || row.cells[primaryColumn].querySelector('a')?.textContent.trim() || headers[primaryColumn].textContent.trim();
        control.setAttribute('aria-label', `${control.textContent}: ${name}`);
    });
    table.addEventListener('table:rendered', refresh);
    refresh();
}
