import { collectionMoney } from './collection-totals.js';

export function titleTotals(rows) {
    const totals = Object.fromEntries(['overdue', 'due_soon', 'future'].map(state => [state, { count: 0, cents: 0n }]));
    for (const row of rows) {
        const group = totals[row.titleSummaryGroup];
        if (!group) continue;
        if (!/^\d+$/.test(row.titleBalanceCents)) throw new Error('Importo del titolo non valido. Ricarica la pagina.');
        const cents = BigInt(row.titleBalanceCents);
        if (cents === 0n) continue;
        group.count++;
        group.cents += cents;
    }
    return totals;
}

document.addEventListener('table:filtered', event => {
    if (!event.target.matches('[data-title-table]')) return;
    const totals = event.detail.rows === null ? null : titleTotals(event.detail.rows);
    for (const card of document.querySelectorAll('[data-title-summary]')) {
        const balance = card.querySelector('[data-title-summary-balance]');
        const count = card.querySelector('[data-title-summary-count]');
        card.dataset.originalAlert ??= String(card.classList.contains('is-alert'));
        balance.dataset.originalText ??= balance.textContent;
        count.dataset.originalText ??= count.textContent;
        const group = totals?.[card.dataset.titleSummary];
        balance.textContent = group ? collectionMoney(group.cents) : balance.dataset.originalText;
        count.textContent = group ? `${group.count} ${group.count === 1 ? 'titolo' : 'titoli'}` : count.dataset.originalText;
        card.classList.toggle('is-alert', group ? card.dataset.titleSummary === 'overdue' && group.count > 0 : card.dataset.originalAlert === 'true');
    }
});
