import test from 'node:test';
import assert from 'node:assert/strict';
import ExcelJS from 'exceljs';
import BrowserExcelJS from '../../node_modules/.cache/r4-insurance/exceljs.cjs';
import { createHash, webcrypto } from 'node:crypto';
import { readFile } from 'node:fs/promises';
import { runInNewContext } from 'node:vm';
import { excelCell, exportColumnType } from '../../resources/js/table-export-values.js';
import { tableWorkbook } from '../../resources/js/table-workbook.js';

test('the browser bundle writes an XLSX without Node globals', async () => {
    const context = { module: { exports: {} }, TextEncoder, TextDecoder, setTimeout, clearTimeout, queueMicrotask, crypto: webcrypto };
    context.self = context; context.window = context;
    runInNewContext(await readFile(new URL('../../node_modules/.cache/r4-insurance/exceljs.cjs', import.meta.url), 'utf8'), context);
    const bytes = await runInNewContext('(async () => { const workbook = new module.exports.Workbook(); workbook.addWorksheet("Dati").addRow(["PV-00001", 1234.56]); return workbook.xlsx.writeBuffer(); })()', context);
    const reopened = new ExcelJS.Workbook();
    await reopened.xlsx.load(Buffer.from(bytes));
    assert.equal(reopened.getWorksheet('Dati').getCell('A1').value, 'PV-00001');
    assert.equal(reopened.getWorksheet('Dati').getCell('B1').value, 1234.56);
});

test('the rebuilt browser library writes valid files, conditional UUIDs and protected worksheets', async () => {
    const workbook = new BrowserExcelJS.Workbook();
    const sheet = workbook.addWorksheet('Dati');
    sheet.addRow(['0000123', 1234.56, new Date('2026-10-19T00:00:00Z'), '=1+1']);
    sheet.addConditionalFormatting({ ref: 'B1', rules: [{ type: 'dataBar', minLength: 10, maxLength: 90, cfvo: [{ type: 'min' }, { type: 'max' }], color: { argb: 'FF0B498C' } }] });
    await sheet.protect('test-only', { spinCount: 10 });
    const protection = sheet.sheetProtection;
    let hash = createHash('sha512').update(Buffer.concat([Buffer.from(protection.saltValue, 'base64'), Buffer.from('test-only', 'utf16le')])).digest();
    for (let i = 0; i < 10; i++) { const iteration = Buffer.alloc(4); iteration.writeUInt32LE(i); hash = createHash('sha512').update(Buffer.concat([hash, iteration])).digest(); }
    assert.equal(protection.hashValue, hash.toString('base64'));
    const reopened = new ExcelJS.Workbook();
    await reopened.xlsx.load(await workbook.xlsx.writeBuffer());
    assert.equal(reopened.getWorksheet('Dati').getCell('A1').value, '0000123');
    assert.equal(reopened.getWorksheet('Dati').getCell('B1').value, 1234.56);
    assert.equal(reopened.getWorksheet('Dati').getCell('D1').type, ExcelJS.ValueType.String);
});

test('Excel dates and euro amounts survive a real workbook round trip as usable values', async () => {
    const workbook = tableWorkbook({
        title: 'Polizze', stamp: '19/10/2026', filters: 'Premio: almeno 100', searchFilters: 'Compagnia: Nobis',
        headers: ['Polizza', 'Codice fiscale', 'Scadenza', 'Premio di polizza', 'Importo', 'Aliquota', 'Polizze', 'Preparata il', 'Ultimo rinnovo'],
        rows: [['0000123', '00123456789', '29/02/2028', '1.234,56 €', '-25,50 €', '12,50 %', '42', '19/10/2026 18:05', '20/10/2026']],
    });
    const reopened = new ExcelJS.Workbook();
    await reopened.xlsx.load(await workbook.xlsx.writeBuffer());
    const sheet = reopened.getWorksheet('Dati');
    assert.equal(sheet.getCell('A6').value, '0000123');
    assert.equal(sheet.getCell('B6').value, '00123456789');
    assert.equal(sheet.getCell('C6').value.toISOString(), '2028-02-29T00:00:00.000Z');
    assert.equal(sheet.getCell('D6').value, 1234.56);
    assert.equal(sheet.getCell('E6').value, -25.50);
    assert.equal(sheet.getCell('F6').value, 0.125);
    assert.equal(sheet.getCell('G6').value, 42);
    assert.equal(sheet.getCell('H6').value.toISOString(), '2026-10-19T18:05:00.000Z');
    assert.equal(sheet.getCell('I6').value.toISOString(), '2026-10-20T00:00:00.000Z');
    assert.equal(sheet.getCell('I6').numFmt, 'dd/mm/yyyy');
    assert.equal(sheet.getCell('D6').numFmt, '#,##0.00 "€"');
    assert.equal(sheet.getCell('F6').numFmt, '0.00%');
    assert.equal(reopened.getWorksheet('Filtri').getCell('B5').value, 'Compagnia: Nobis');
});

test('identifiers, phone numbers, compound values and invalid dates remain literal text', () => {
    for (const value of ['00123', '12345678901234567890', '+3908123456', '01/02/2026', '=HYPERLINK("https://example.test")', '@SUM(A1:A2)', '-cmd']) {
        assert.deepEqual(excelCell(value, 'text'), { value, numFmt: '@' });
    }
    for (const value of ['31/02/2026', '29/02/2027', '19/10/2026 25:00', '19/10/2026 12:70', '16/10/2026 – 16/04/2027']) {
        assert.deepEqual(excelCell(value, 'date'), { value, numFmt: '@' });
    }
    for (const value of ['1.23,45 €', '123 € da incassare', '=1+1', 'Non indicato']) {
        assert.deepEqual(excelCell(value, 'currency'), { value, numFmt: '@' });
    }
    assert.equal(excelCell('—', 'currency').value, null);
    assert.equal(excelCell('0,00 €', 'currency').value, 0);
    assert.equal(excelCell('1.234,56', 'currency').value, 1234.56);
});

test('workbooks never turn untrusted strings into executable formulas', async () => {
    const values = ['=1+1', '+SUM(1,1)', '-cmd|anything', '@SUM(A1)', '0000'];
    const workbook = tableWorkbook({ title: '=1+1', stamp: '19/10/2026', headers: ['Cliente'], rows: values.map(v => [v]) });
    const reopened = new ExcelJS.Workbook(); await reopened.xlsx.load(await workbook.xlsx.writeBuffer());
    const sheet = reopened.getWorksheet('Dati');
    assert.equal(sheet.getCell('A1').type, ExcelJS.ValueType.String);
    values.forEach((value, i) => {
        assert.equal(sheet.getCell(`A${i + 6}`).value, value);
        assert.equal(sheet.getCell(`A${i + 6}`).type, ExcelJS.ValueType.String);
    });
});

test('table column semantics distinguish counters and dates from policy identifiers', () => {
    for (const header of ['Polizza', 'Numero polizza', 'Codice fiscale', 'Partita IVA', 'Telefono', 'Periodo']) assert.equal(exportColumnType(header), 'text');
    for (const header of ['Data', 'Scadenza', 'Data incasso', 'Inserita il', 'Registrata il', 'Ultimo rinnovo', 'Dal']) assert.equal(exportColumnType(header), 'date');
    assert.equal(exportColumnType('Appendici'), 'integer');
    for (const header of ['Premio di polizza', 'Premi di polizza', 'Importo', 'Residuo']) assert.equal(exportColumnType(header), 'currency');
    for (const header of ['Polizze', 'Clienti distinti', 'Premio non indicato', 'Non pagate']) assert.equal(exportColumnType(header), 'integer');
    assert.equal(excelCell('17,25 %').value, 0.1725);
});
