import { collectionMoney, collectionTotals } from './collection-totals.js';

export function collectionGroups(rows, includeVoided = false) {
    const groups = new Map();
    for (const row of rows) {
        if (!includeVoided && row.collectionVoided === '1') continue;
        if (!row.collectionGroup) throw new Error('Raggruppamento non disponibile. Ricarica la contabilità.');
        if (!groups.has(row.collectionGroup)) groups.set(row.collectionGroup, {key:row.collectionGroup, company:row.collectionCompany, agency:row.collectionAgency, rows:[]});
        groups.get(row.collectionGroup).rows.push(row);
    }
    return [...groups.values()].map(group => ({...group, ...collectionTotals(group.rows),
        receiptCount:group.rows.filter(row=>row.collectionVoided !== '1' && row.collectionDirection !== 'refund').length,
        refundCount:group.rows.filter(row=>row.collectionVoided !== '1' && row.collectionDirection === 'refund').length,
    })).sort((a,b)=>a.company.localeCompare(b.company,'it') || a.agency.localeCompare(b.agency,'it'));
}

export function collectionReport(records, headers, groupKey = null) {
    const selected = groupKey === null ? records : records.filter(record=>record.data.collectionGroup === groupKey);
    if (groupKey !== null && !selected.length) throw new Error('Nessun movimento corrisponde a questa compagnia e agenzia con i filtri selezionati.');
    const groups = collectionGroups(selected.map(record=>record.data), true);
    const read = (record, name) => {
        const index = headers.indexOf(name);
        if (index < 0) throw new Error('La tabella è cambiata. Ricarica la contabilità.');
        return record.cells[index];
    };
    const detailHeaders = ['Data','Contraente','Consulente','Compagnia','Agenzia','Polizza / titolo','Incasso','Rimborso','Tipo pagamento','Gestito da','Stato','Tipo documento','Riferimento','Documenti'];
    const rows = selected.map(record=>detailHeaders.map(name=> {
        if (['Incasso','Rimborso'].includes(name)) {
            const refund = record.data.collectionDirection === 'refund';
            return (name === 'Rimborso') === refund ? collectionMoney(BigInt(record.data.collectionCents)) : '';
        }
        return read(record,name);
    }));
    const totals = collectionTotals(selected.map(record=>record.data));
    const summaryHeaders = ['Compagnia','Agenzia','N. incassi','Incassi','N. rimborsi','Rimborsi','Saldo'];
    const summaryRows = groups.map(group=>[group.company,group.agency,String(group.receiptCount),collectionMoney(group.receipts),String(group.refundCount),collectionMoney(group.refunds),collectionMoney(group.total)]);
    const totalRow = detailHeaders.map(()=> ''); totalRow[0] = 'Totale, esclusi storni'; totalRow[6] = collectionMoney(totals.receipts); totalRow[7] = collectionMoney(totals.refunds);
    return {
        title:groupKey === null ? 'Contabilità per compagnia e agenzia' : `${groups[0].company} / ${groups[0].agency}`,
        headers:detailHeaders, rows, footers:[totalRow], totals,
        summary:{headers:summaryHeaders,rows:summaryRows,columnTypes:['text','text','integer','currency','integer','currency','currency'],
            footers:[['Totale','',String(groups.reduce((n,g)=>n+g.receiptCount,0)),collectionMoney(totals.receipts),String(groups.reduce((n,g)=>n+g.refundCount,0)),collectionMoney(totals.refunds),collectionMoney(totals.total)]]},
        groups:groups.map(group=>({...group, details:selected.flatMap((record,index)=>record.data.collectionGroup===group.key ? [rows[index]] : [])})),
    };
}
