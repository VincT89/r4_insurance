import { collectionMoney } from './collection-totals.js';

export function allowanceTotal(rows) {
    return allowanceTotals(rows).amount;
}

export function allowanceTotals(rows) {
    const totals = {amount:0n,recovered:0n,outstanding:0n};
    for (const row of rows) {
        if (row.allowanceVoided === '1') continue;
        for (const [field,key] of [['amount','allowanceCents'],['recovered','allowanceRecoveredCents'],['outstanding','allowanceOutstandingCents']]) {
            const value = row[key] ?? (field === 'amount' ? '' : '0');
            if (!/^\d+$/.test(value)) throw new Error('Importo dell’abbuono non valido. Ricarica la pagina.');
            totals[field] += BigInt(value);
        }
    }
    return totals;
}

export function allowanceExportFooters(rows, headers) {
    const amount = headers.indexOf('Importo');
    if (amount < 0) return [];
    const values = headers.map(() => '');
    values[0] = 'Totale abbuoni, esclusi storni';
    const totals = allowanceTotals(rows);
    values[amount] = collectionMoney(totals.amount);
    for (const [label,key] of [['Recuperato','recovered'],['Da recuperare','outstanding']]) {
        const index = headers.indexOf(label);
        if (index >= 0) values[index] = collectionMoney(totals[key]);
    }
    return [values];
}

document.addEventListener('table:filtered', event => {
    if (!event.target.matches('[data-allowance-table]')) return;
    const totals = event.detail.rows === null ? null : allowanceTotals(event.detail.rows);
    for (const [selector,key] of [['[data-allowance-total]','amount'],['[data-allowance-recovered-total]','recovered'],['[data-allowance-outstanding-total]','outstanding']]) {
        for (const field of event.target.closest('.panel').querySelectorAll(selector)) {
            field.dataset.originalTotal ??= field.textContent;
            field.textContent = totals === null ? field.dataset.originalTotal : collectionMoney(totals[key]);
        }
    }
});
