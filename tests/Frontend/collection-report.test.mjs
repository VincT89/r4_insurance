import test from 'node:test';
import assert from 'node:assert/strict';
import ExcelJS from 'exceljs';
globalThis.document = {addEventListener(){}};
const {collectionReport,collectionGroups} = await import('../../resources/js/collection-report.js');
const {tableWorkbook} = await import('../../resources/js/table-workbook.js');

const headers=['Data','Contraente','Consulente','Polizza / titolo','Compagnia','Agenzia','Importo','Tipo pagamento','Gestito da','Stato','Tipo documento','Riferimento','Documenti'];
const record=(key,amount,{refund=false,voided=false,company='Compagnia demo',agency='Agenzia demo'}={})=>({
    data:{collectionGroup:key,collectionCompany:company,collectionAgency:agency,collectionCents:String(amount),collectionDirection:refund?'refund':'receipt',collectionRecipient:'our_agency',collectionVoided:voided?'1':'0'},
    cells:['02/11/2026','=CLIENTE DIMOSTRATIVO','Consulente demo','DEMO-0001 / #1',company,agency,'',refund?'Bonifico':'Contanti a Intermediario','Nostra agenzia',voided?'Stornato':refund?'Rimborso registrato':'Incasso registrato','Registrazione del pagamento','RIF-DEMO',''],
});

test('accounting report reconciles all pages and groups by both company and agency',()=>{
    const records=Array.from({length:76},()=>record('a',101));
    records.push(record('a',10000,{refund:true}),record('a',99900,{voided:true}),record('b',2000,{company:'Altra compagnia demo'}));
    const report=collectionReport(records,headers);
    assert.equal(report.rows.length,79);assert.equal(report.groups.length,2);
    assert.deepEqual(report.totals,{receipts:9676n,refunds:10000n,total:-324n,ours:-324n,direct:0n});
    assert.equal(report.summary.footers[0][6],'-3,24 €');
    assert.equal(report.groups.find(group=>group.key==='a').receiptCount,76);
    assert.equal(report.rows[76][7],'100,00 €');assert.equal(report.rows[76][6],'');
    const one=collectionReport(records,headers,'b');assert.equal(one.rows.length,1);assert.equal(one.totals.total,2000n);
    assert.throws(()=>collectionReport(records,headers,'missing'),/Nessun movimento/);
});

test('summary follows the filtered rows and excludes reversals while the report preserves them',()=>{
    const filtered=[record('refund',2500,{refund:true}),record('voided',4000,{voided:true})];
    const groups=collectionGroups(filtered.map(record=>record.data));
    assert.equal(groups.length,1);assert.equal(groups[0].total,-2500n);
    assert.equal(collectionReport(filtered,headers).groups.length,2);
    assert.equal(collectionReport([],headers).totals.total,0n);
    assert.throws(()=>collectionReport([record('a',1)],headers.slice(1)),/tabella è cambiata/);
});

test('accounting workbook keeps numeric receipts and refunds, methods, safe text and the applied period',async()=>{
    const report=collectionReport([record('a',12345),record('a',13000,{refund:true})],headers);
    const workbook=tableWorkbook({...report,searchFilters:'Dal: 2026-11-01; Al: 2026-11-02',filters:'',stamp:'02/11/2026'});
    const saved=new ExcelJS.Workbook();await saved.xlsx.load(await workbook.xlsx.writeBuffer());
    assert.deepEqual(saved.worksheets.map(sheet=>sheet.name),['Riepilogo','Movimenti','Filtri']);
    const summary=saved.getWorksheet('Riepilogo'), details=saved.getWorksheet('Movimenti');
    assert.equal(summary.getCell('D6').value,123.45);assert.equal(summary.getCell('F6').value,130);assert.equal(summary.getCell('G6').value,-6.55);
    assert.equal(details.getCell('G6').value,123.45);assert.equal(details.getCell('H7').value,130);
    assert.equal(details.getCell('I6').value,'Contanti a Intermediario');assert.equal(details.getCell('I7').value,'Bonifico');
    assert.equal(details.getCell('B6').value,'=CLIENTE DIMOSTRATIVO');assert.equal(details.getCell('B6').type,ExcelJS.ValueType.String);
    assert.match(details.getCell('A3').value,/Dal: 2026-11-01/);assert.equal(details.autoFilter,'A5:N7');
});
