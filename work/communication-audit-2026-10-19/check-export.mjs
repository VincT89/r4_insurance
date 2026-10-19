import ExcelJS from 'exceljs';
import assert from 'node:assert/strict';
const [path, expectedRows, expectedCompany] = process.argv.slice(2);
const workbook = new ExcelJS.Workbook();
await workbook.xlsx.readFile(path);
const sheet = workbook.getWorksheet('Dati');
assert.equal(sheet.rowCount - 5, Number(expectedRows));
const headers = sheet.getRow(5).values.slice(1);
const policyColumn = headers.findIndex(h => /^Polizza/.test(h)) + 1;
const companyColumn = headers.indexOf('Compagnia') + 1;
const amountColumn = headers.indexOf('Importo') + 1;
const dateColumn = headers.findIndex(h => h === 'Inserita il' || h === 'Data' || h === 'Preparata il') + 1;
const identifiers = new Set();
for (let i = 6; i <= sheet.rowCount; i++) {
    if (policyColumn) identifiers.add(sheet.getRow(i).getCell(policyColumn).text);
    if (dateColumn) assert.ok(sheet.getRow(i).getCell(dateColumn).value instanceof Date);
    if (amountColumn) assert.equal(typeof sheet.getRow(i).getCell(amountColumn).value, 'number');
    if (expectedCompany) assert.equal(sheet.getRow(i).getCell(companyColumn).value, expectedCompany);
}
if (policyColumn) assert.equal(identifiers.size, Number(expectedRows));
console.log(JSON.stringify({ path, rows: sheet.rowCount - 5, uniqueIdentifiers: policyColumn ? identifiers.size : null, datesAreDates: !!dateColumn, amountsAreNumbers: !!amountColumn, company: expectedCompany || null, filters: workbook.getWorksheet('Filtri').getCell('B4').text }));
