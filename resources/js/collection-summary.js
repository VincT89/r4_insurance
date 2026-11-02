import { collectionGroups } from './collection-report.js';
import { collectionMoney } from './collection-totals.js';

const originals = new WeakMap();
const node = (tag,text) => { const element=document.createElement(tag); if(text !== undefined) element.textContent=text; return element; };

document.addEventListener('table:filtered', event => {
    if (!event.target.matches('[data-collection-table]')) return;
    const body = event.target.closest('[data-collection-workspace]')?.querySelector('[data-collection-groups]');
    if (!body) return;
    if (!originals.has(body)) originals.set(body, [...body.children].map(row=>row.cloneNode(true)));
    if (event.detail.rows === null) { body.replaceChildren(...originals.get(body).map(row=>row.cloneNode(true))); return; }
    const rows = collectionGroups(event.detail.rows).map(group => {
        const row = node('tr'), title = node('th',group.company); title.scope='row'; title.append(node('small',group.agency)); row.append(title);
        for (const [amount,count,label] of [[group.receipts,group.receiptCount,'incassi'],[group.refunds,group.refundCount,'rimborsi']]) {
            const noun = count === 1 ? (label === 'incassi' ? 'incasso' : 'rimborso') : label;
            const cell = node('td',collectionMoney(amount)); cell.append(node('small',`${count} ${noun}`)); row.append(cell);
        }
        row.append(node('td',collectionMoney(group.total)));
        const cell=node('td'), button=node('button','Stampa / PDF'); button.type='button'; button.className='btn btn-g btn-sm'; button.dataset.collectionExport='pdf'; button.dataset.collectionGroup=group.key;
        button.setAttribute('aria-label',`Stampa ${group.company} / ${group.agency}`); cell.append(button); row.append(cell); return row;
    });
    if (!rows.length) { const row=node('tr'), cell=node('td','Nessun movimento valido corrisponde ai filtri.'); cell.colSpan=5; row.append(cell); rows.push(row); }
    body.replaceChildren(...rows);
});
