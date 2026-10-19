import ExcelJS from 'exceljs';
import { excelCell, exportColumnType } from './table-export-values.js';

export function tableWorkbook({ title, headers, rows, filters, searchFilters, stamp, columnTypes = [] }) {
    const workbook = new ExcelJS.Workbook();
    workbook.creator = 'R4 Insurance';
    const sheet = workbook.addWorksheet('Dati');
    sheet.columns = headers.map((header, i) => ({ width: Math.min(55, Math.max(18, header.length + 3, ...rows.slice(0, 100).map(row => Math.min(55, String(row[i] ?? '').length + 2)))) }));
    sheet.addRow([title]);
    sheet.addRow([`Esportato il ${stamp} · ${rows.length} ${rows.length === 1 ? 'riga' : 'righe'}`]);
    sheet.addRow([filters ? `Filtri avanzati: ${filters}` : 'Tutti i risultati della ricerca corrente']);
    sheet.addRow([]); sheet.addRow(headers);
    for (const values of rows) {
        const row = sheet.addRow([]);
        row.alignment = { vertical: 'top', wrapText: true };
        headers.forEach((header, index) => {
            const cell = excelCell(values[index], columnTypes[index] || exportColumnType(header));
            row.getCell(index + 1).value = cell.value;
            row.getCell(index + 1).numFmt = cell.numFmt;
        });
    }
    for (const n of [1, 2, 3]) {
        sheet.mergeCells(n, 1, n, Math.max(1, headers.length));
        sheet.getRow(n).alignment = { vertical: 'middle', wrapText: true };
        sheet.getRow(n).height = n === 3 ? 42 : 28;
    }
    sheet.getRow(1).font = { bold: true, size: 16, color: { argb: 'FF0B498C' } };
    sheet.getRow(5).font = { bold: true, color: { argb: 'FFFFFFFF' } };
    sheet.getRow(5).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF0B498C' } };
    sheet.getRow(5).height = 28;
    sheet.autoFilter = { from: { row: 5, column: 1 }, to: { row: 5 + rows.length, column: Math.max(1, headers.length) } };
    sheet.views = [{ state: 'frozen', ySplit: 5 }];
    const meta = workbook.addWorksheet('Filtri');
    meta.columns = [{ width: 24 }, { width: 110 }];
    meta.addRows([['Elenco', title], ['Data', stamp], ['Righe', rows.length], ['Filtri avanzati', filters || 'Nessuno'], ['Ricerca corrente', searchFilters || 'Nessuna']]);
    meta.eachRow(row => row.alignment = { wrapText: true, vertical: 'top' });
    return workbook;
}
