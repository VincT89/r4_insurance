import test from 'node:test';
import assert from 'node:assert/strict';
globalThis.document = {addEventListener(){}};
const {allowanceTotal,allowanceTotals,allowanceExportFooters} = await import('../../resources/js/allowance-totals.js');

test('allowance totals and exports use the filtered rows and exclude reversals',()=>{
    const rows=Array.from({length:76},()=>({allowanceCents:'101',allowanceVoided:'0'}));
    rows.push({allowanceCents:'99900',allowanceVoided:'1'});
    assert.equal(allowanceTotal(rows),7676n);
    assert.deepEqual(allowanceExportFooters(rows,['Cliente / polizza','Data','Importo','Stato']),[['Totale abbuoni, esclusi storni','','76,76 €','']]);
    assert.equal(allowanceTotal([]),0n);
    assert.equal(allowanceTotal(rows.slice(0,2)),202n);
    assert.throws(()=>allowanceTotal([{allowanceCents:'-100',allowanceVoided:'0'}]),/non valido/);
});

test('agency recoveries and outstanding credits follow filtered rows and exports',()=>{
    const rows=[
        {allowanceCents:'1000',allowanceRecoveredCents:'400',allowanceOutstandingCents:'600',allowanceVoided:'0'},
        {allowanceCents:'1000',allowanceRecoveredCents:'0',allowanceOutstandingCents:'0',allowanceVoided:'0'},
        {allowanceCents:'500',allowanceRecoveredCents:'500',allowanceOutstandingCents:'0',allowanceVoided:'0'},
        {allowanceCents:'700',allowanceRecoveredCents:'0',allowanceOutstandingCents:'0',allowanceVoided:'1'},
    ];
    assert.deepEqual(allowanceTotals(rows),{amount:2500n,recovered:900n,outstanding:600n});
    assert.deepEqual(allowanceExportFooters(rows,['Cliente / polizza','Importo','Recuperato','Da recuperare']),[['Totale abbuoni, esclusi storni','25,00 €','9,00 €','6,00 €']]);
    assert.deepEqual(allowanceTotals(rows.slice(0,1)),{amount:1000n,recovered:400n,outstanding:600n});
    assert.deepEqual(allowanceTotals([]),{amount:0n,recovered:0n,outstanding:0n});
    assert.throws(()=>allowanceTotals([{allowanceCents:'100',allowanceRecoveredCents:'-1'}]),/non valido/);
});
