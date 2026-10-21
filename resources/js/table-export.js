const clean = value => String(value ?? '').replace(/[\u0000-\u0008\u000b\u000c\u000e-\u001f]/g, '');
const save = (bytes, mime, filename) => {
    const url = URL.createObjectURL(new Blob([bytes], { type: mime }));
    const a = document.createElement('a'); a.href = url; a.download = filename; a.click();
    setTimeout(() => URL.revokeObjectURL(url), 30000);
};

export async function exportTable({ format, title, headers, rows, filters, searchFilters, columnTypes = [] }) {
    title = clean(title); headers = headers.map(clean); rows = rows.map(r => r.map(clean));
    const stamp = new Date().toLocaleString('it-IT');
    const rowCount = `${rows.length} ${rows.length === 1 ? 'riga' : 'righe'}`;
    const name = (title.normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9]+/g, '-').slice(0,80) || 'Esportazione') + '-' + new Date().toISOString().slice(0,10);
    if (format === 'xlsx') {
        const { tableWorkbook } = await import('./table-workbook.js');
        const workbook = tableWorkbook({ title, headers, rows, filters, searchFilters, stamp, columnTypes });
        save(await workbook.xlsx.writeBuffer(), 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', name+'.xlsx');
        return;
    }
    const [{ jsPDF }, { autoTable }] = await Promise.all([import('jspdf'), import('jspdf-autotable')]);
    const fontUrl = new URL('../fonts/Ubuntu-R.ttf', import.meta.url);
    const response = await fetch(fontUrl); if (!response.ok) throw new Error('Carattere PDF non disponibile. Riprova.');
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = ''; for (let i=0; i<bytes.length; i+=8192) binary += String.fromCharCode(...bytes.subarray(i,i+8192));
    /** Wide tables need more page width to keep percentages, dates and headings readable. */
    const doc = new jsPDF({ format: headers.length > 10 ? 'a3' : 'a4', orientation: headers.length >= 4 ? 'landscape' : 'portrait', compress: true, putOnlyUsedFonts: true });
    doc.addFileToVFS('Ubuntu-R.ttf', btoa(binary)); doc.addFont('Ubuntu-R.ttf', 'Ubuntu', 'normal'); doc.setFont('Ubuntu');
    const width = doc.internal.pageSize.getWidth();
    doc.setFontSize(15); const titleLines = doc.splitTextToSize(title, width-24); doc.text(titleLines,12,15);
    doc.setFontSize(9);
    const meta = doc.splitTextToSize(`Esportato il ${stamp} · ${rowCount}\n${filters ? 'Filtri: '+filters : 'Tutti i risultati della ricerca corrente'}\nRicerca corrente: ${searchFilters || 'nessuna'}`, width-24);
    let start = 20 + titleLines.length*6;
    for (const line of meta) { if (start > doc.internal.pageSize.getHeight()-22) { doc.addPage(); start=15; } doc.text(line,12,start); start+=4; }
    const weights = headers.map((h,i) => Math.max(12,Math.min(200,Math.max(h.length+2,...rows.slice(0,200).map(r=>r[i].length+2)))));
    /** Reserve readable narrow columns before giving longer descriptions the remaining page width. */
    const total = weights.reduce((a,b)=>a+b,0);
    const minimum = Math.min(24,(width-24)/headers.length);
    const remaining = width-24-minimum*headers.length;
    const columnStyles = Object.fromEntries(weights.map((weight,i)=>[i,{cellWidth:minimum+remaining*weight/total}]));
    autoTable(doc, { head: [headers], body: rows, startY: start+4, margin: { top: 14, left: 12, right: 12, bottom: 16 }, columnStyles, tableWidth:width-24,
        styles: { font: 'Ubuntu', fontStyle: 'normal', fontSize: 10, cellPadding: 3, overflow: 'linebreak', minCellWidth: 26 },
        headStyles: { font: 'Ubuntu', fontStyle: 'normal', fillColor: [11,73,140], textColor: 255 },
        alternateRowStyles: { fillColor: [244,246,249] }, showHead: 'everyPage', rowPageBreak: 'avoid',
    });
    for(let n=1;n<=doc.getNumberOfPages();n++){doc.setPage(n);doc.setFont('Ubuntu');doc.setFontSize(9);doc.text(`R4 Insurance · Pagina ${n} di ${doc.getNumberOfPages()}`,12,doc.internal.pageSize.getHeight()-7);}
    save(doc.output('arraybuffer'), 'application/pdf', name+'.pdf');
}
