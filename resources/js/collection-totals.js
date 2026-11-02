export function collectionTotals(rows) {
    let total = 0n, ours = 0n, receipts = 0n, refunds = 0n;
    for (const row of rows) {
        if (row.collectionVoided === '1') continue;
        if (!/^\d+$/.test(row.collectionCents)) throw new Error('Importo del pagamento non valido. Ricarica la pagina.');
        const amount = BigInt(row.collectionCents), refund = row.collectionDirection === 'refund';
        const cents = refund ? -amount : amount;
        if (refund) refunds += amount; else receipts += amount;
        total += cents;
        if (row.collectionRecipient === 'our_agency') ours += cents;
    }
    return { total, ours, direct: total - ours, receipts, refunds };
}

export function collectionMoney(cents) {
    if (cents < 0n) return `-${collectionMoney(-cents)}`;
    return `${String(cents / 100n).replace(/\B(?=(\d{3})+(?!\d))/g, '.')},${String(cents % 100n).padStart(2, '0')} €`;
}

export function collectionExportFooters(rows, headers) {
    const amount = headers.indexOf('Importo');
    if (amount < 0) return [];
    const totals = collectionTotals(rows);
    return [['Incassi, esclusi storni',totals.receipts], ['Rimborsi, esclusi storni',totals.refunds], ['Saldo incassi meno rimborsi',totals.total], ['Saldo della nostra agenzia',totals.ours], ['Saldo compagnie e altre agenzie',totals.direct]].map(([label,cents]) => {
        const values = headers.map(()=>''); values[0] = label; values[amount] = collectionMoney(cents); return values;
    });
}

document.addEventListener('table:filtered', event => {
    if (!event.target.matches('[data-collection-table]')) return;
    const fields = document.querySelectorAll('[data-collection-total]');
    const totals = event.detail.rows === null ? null : collectionTotals(event.detail.rows);
    for (const field of fields) {
        field.dataset.originalTotal ??= field.textContent;
        field.textContent = totals === null ? field.dataset.originalTotal : collectionMoney(totals[field.dataset.collectionTotal]);
    }
});
